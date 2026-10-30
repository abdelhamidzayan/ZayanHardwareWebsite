from __future__ import annotations
import hashlib, json, re, subprocess
from collections import Counter
from pathlib import Path
from graphify.analyze import god_nodes, surprising_connections, suggest_questions
from graphify.build import build_from_json
from graphify.cluster import cluster, score_all
from graphify.diagnostics import diagnose_extraction, format_diagnostic_report
from graphify.export import to_json
from graphify.extract import extract
from graphify.report import generate

ROOT=Path.cwd().resolve()
OUT=ROOT/"graphify-out"
OUT.mkdir(exist_ok=True)
ROOTS=(ROOT/"app",ROOT/"config",ROOT/"public",ROOT/"database"/"migrations")
EXTS={".php",".js",".sql"}
EXCLUDED={".git","vendor","node_modules","storage","uploads","cache","logs","graphify-out",".venv","__pycache__"}

def files():
    result=[]
    for base in ROOTS:
        if not base.exists(): continue
        for p in base.rglob("*"):
            if p.is_file() and not p.is_symlink() and p.suffix.lower() in EXTS:
                rel=p.relative_to(ROOT)
                if not any(part in EXCLUDED for part in rel.parts): result.append(p)
    return sorted(set(result),key=lambda p:p.relative_to(ROOT).as_posix())

def sid(kind,value):
    return "zayan_"+kind+"_"+hashlib.sha1(value.encode()).hexdigest()[:16]

def line(text,offset):
    return text.count("\n",0,offset)+1

def node(nodes,seen,kind,label,source,line_no,identity):
    nid=sid(kind,identity)
    if nid not in seen:
        nodes.append({"id":nid,"label":label,"type":kind,"source_file":source,
                      "source_location":f"L{line_no}","_origin":"ast"})
        seen.add(nid)
    return nid

def edge(edges,src,dst,relation,source,line_no,context):
    edges.append({"source":src,"target":dst,"relation":relation,"confidence":"EXTRACTED",
                  "source_file":source,"source_location":f"L{line_no}","context":context[:240]})

def read(p):
    return p.read_text(encoding="utf-8",errors="replace")

def framework_edges(data,source_files):
    nodes=data["nodes"]; edges=data["edges"]
    seen={str(n.get("id")) for n in nodes if isinstance(n,dict) and n.get("id")}
    routes={}
    controllers={}
    rp=ROOT/"config"/"routes.php"
    if rp.exists():
        body=read(rp); rel=rp.relative_to(ROOT).as_posix()
        pat=re.compile(r"\$router\s*->\s*(get|post)\s*\(\s*'([^']+)'\s*,\s*\[\s*([A-Za-z_][A-Za-z0-9_]*)::class\s*,\s*'([A-Za-z_][A-Za-z0-9_]*)'\s*\]\s*\)",re.I)
        for m in pat.finditer(body):
            method,path,ctrl,action=m.groups(); ln=line(body,m.start())
            rid=node(nodes,seen,"route",f"{method.upper()} {path}",rel,ln,f"{method.upper()} {path}")
            aid=node(nodes,seen,"controller_action",f"{ctrl}::{action}",f"app/controllers/{ctrl}.php",1,f"{ctrl}::{action}")
            edge(edges,rid,aid,"dispatches_to",rel,ln,f"Explicit route maps {method.upper()} {path} to {ctrl}::{action}.")
            routes[(method.upper(),path)]=rid
            controllers[ctrl]=node(nodes,seen,"controller",ctrl,f"app/controllers/{ctrl}.php",1,ctrl)
    for p in sorted((ROOT/"app"/"controllers").glob("*.php")):
        rel=p.relative_to(ROOT).as_posix(); ctrl=p.stem
        cid=controllers.get(ctrl) or node(nodes,seen,"controller",ctrl,rel,1,ctrl)
        body=read(p)
        for m in re.finditer(r"\$this\s*->\s*view\s*\(\s*'([^']+)'",body):
            name=m.group(1); ln=line(body,m.start()); viewrel=f"app/views/{name}.php"
            vid=node(nodes,seen,"view",name,viewrel,1,name)
            edge(edges,cid,vid,"renders",rel,ln,f"{ctrl} renders {viewrel}.")
    table_nodes={}
    use=re.compile(r"\b(FROM|JOIN|INTO|UPDATE|DELETE\s+FROM)\s+(?:\x60)?([A-Za-z_][A-Za-z0-9_]*)(?:\x60)?",re.I)
    for p in sorted((ROOT/"app"/"models").glob("*.php")):
        rel=p.relative_to(ROOT).as_posix(); mid=node(nodes,seen,"model",p.stem,rel,1,p.stem)
        body=read(p); used=set()
        for m in use.finditer(body):
            op,table=m.groups(); table=table.lower()
            relation="writes_to" if op.upper().replace(" ","_") in {"INTO","UPDATE","DELETE_FROM"} else "reads_from"
            if (table,relation) in used: continue
            used.add((table,relation))
            tid=table_nodes.get(table) or node(nodes,seen,"database_table",table,rel,line(body,m.start()),table)
            table_nodes[table]=tid
            edge(edges,mid,tid,relation,rel,line(body,m.start()),f"{p.stem} SQL contains {op.upper()} {table}.")
    schema=re.compile(r"\b(CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|ALTER\s+TABLE|DROP\s+TABLE(?:\s+IF\s+EXISTS)?)\s+(?:\x60)?([A-Za-z_][A-Za-z0-9_]*)(?:\x60)?",re.I)
    for p in sorted((ROOT/"database"/"migrations").glob("*.sql")):
        rel=p.relative_to(ROOT).as_posix(); body=read(p)
        mid=node(nodes,seen,"migration",p.name,rel,1,rel)
        for m in schema.finditer(body):
            op,table=m.groups(); table=table.lower()
            tid=table_nodes.get(table) or node(nodes,seen,"database_table",table,rel,line(body,m.start()),table)
            table_nodes[table]=tid
            edge(edges,mid,tid,"changes_schema",rel,line(body,m.start()),f"{p.name} contains {op.upper()} {table}.")
    asset_pat=re.compile(r"""(?:src|href)\s*=\s*["'][^"']*?((?:css|js)/[A-Za-z0-9_./-]+\.(?:css|js))["']""",re.I)
    for p in sorted((ROOT/"app"/"views").rglob("*.php")):
        rel=p.relative_to(ROOT).as_posix(); name=p.relative_to(ROOT/"app"/"views").with_suffix("").as_posix()
        body=read(p); vid=node(nodes,seen,"view",name,rel,1,name)
        for m in asset_pat.finditer(body):
            asset=m.group(1); ap="public/"+asset
            aid=node(nodes,seen,"frontend_asset",asset,ap,1,ap)
            edge(edges,vid,aid,"loads_asset",rel,line(body,m.start()),f"{name} references {asset}.")
    fetch_pat=re.compile(r"""fetch\s*\(\s*['"](/[^'"]+)['"]""")
    for p in sorted((ROOT/"public"/"js").rglob("*.js")):
        rel=p.relative_to(ROOT).as_posix(); body=read(p)
        for m in fetch_pat.finditer(body):
            endpoint=m.group(1)
            for (method,path),rid in routes.items():
                if endpoint==path:
                    jsid=node(nodes,seen,"frontend_script",p.name,rel,1,rel)
                    edge(edges,jsid,rid,"calls_endpoint",rel,line(body,m.start()),f"{p.name} calls {method} {path}.")
    return data


def consistency_checks():
    failures=[]
    notices=[]
    routes_path=ROOT/"config"/"routes.php"
    routes_body=read(routes_path)
    route_pat=re.compile(r"\$router\s*->\s*(get|post)\s*\(\s*'([^']+)'\s*,\s*\[\s*([A-Za-z_][A-Za-z0-9_]*)::class\s*,\s*'([A-Za-z_][A-Za-z0-9_]*)'\s*\]\s*\)",re.I)
    routes=[]
    for m in route_pat.finditer(routes_body):
        method,path,ctrl,action=m.groups()
        controller_path=ROOT/"app"/"controllers"/f"{ctrl}.php"
        if not controller_path.is_file():
            failures.append(f"Route {method.upper()} {path}: missing controller file {controller_path.relative_to(ROOT)}")
            continue
        body=read(controller_path)
        if not re.search(r"\bpublic\s+function\s+"+re.escape(action)+r"\s*\(",body):
            failures.append(f"Route {method.upper()} {path}: missing public method {ctrl}::{action}")
        routes.append({"method":method.upper(),"path":path,"controller":ctrl,"action":action,
                       "line":line(routes_body,m.start())})
    # Match named URL placeholders to the controller signature and Router's explicit
    # integer-normalization contract. This catches strict_types TypeErrors before runtime.
    router_body=read(ROOT/"app"/"core"/"Router.php")
    integer_match=re.search(r"INTEGER_ROUTE_PARAMETERS\s*=\s*\[(.*?)\]",router_body,re.S)
    integer_route_params=set(re.findall(r"['\"]([A-Za-z_]\w*)['\"]",integer_match.group(1))) if integer_match else set()
    used_integer_params=set()
    if not integer_match:
        failures.append("Router is missing INTEGER_ROUTE_PARAMETERS; route argument types cannot be verified.")
    for route in routes:
        controller_path=ROOT/"app"/"controllers"/(route["controller"]+".php")
        body=read(controller_path) if controller_path.is_file() else ""
        signature=re.search(r"\bpublic\s+function\s+"+re.escape(route["action"])+r"\s*\((.*?)\)",body,re.S)
        if not signature:
            continue
        parameter_types={}
        for declaration in signature.group(1).split(","):
            parameter=re.search(r"(?:(?P<type>\??[A-Za-z_][A-Za-z0-9_\\|]*)\s+)?\$(?P<name>[A-Za-z_]\w*)",declaration)
            if parameter:
                parameter_types[parameter.group("name")]=(parameter.group("type") or "untyped").lstrip("?")
        for placeholder in re.findall(r"\{([A-Za-z_]\w*)\}",route["path"]):
            parameter_type=parameter_types.get(placeholder)
            if parameter_type is None:
                failures.append("Route "+route["method"]+" "+route["path"]+": placeholder $"+placeholder+" is missing from "+route["controller"]+"::"+route["action"]+" signature")
            elif parameter_type in {"int","integer"} and placeholder not in integer_route_params:
                failures.append("Route "+route["method"]+" "+route["path"]+": integer parameter $"+placeholder+" is not normalized by Router")
            elif placeholder in integer_route_params and parameter_type not in {"int","integer"}:
                failures.append("Route "+route["method"]+" "+route["path"]+": Router casts $"+placeholder+", but controller expects "+parameter_type)
            if placeholder in integer_route_params:
                used_integer_params.add(placeholder)
    unused_integer_params=integer_route_params-used_integer_params
    if unused_integer_params:
        failures.append("Router integer parameter map contains unused keys: "+", ".join(sorted(unused_integer_params)))

    views=[]
    view_pat=re.compile(r"\$this\s*->\s*view\s*\(\s*'([^']+)'" )
    for p in sorted((ROOT/"app"/"controllers").glob("*.php")):
        body=read(p)
        for m in view_pat.finditer(body):
            name=m.group(1); view_path=ROOT/"app"/"views"/(name+".php")
            if not view_path.is_file():
                failures.append(f"{p.relative_to(ROOT)} renders missing view {view_path.relative_to(ROOT)}")
            views.append({"controller":p.stem,"view":name,"line":line(body,m.start())})
    prefixes={}
    for p in sorted((ROOT/"database"/"migrations").glob("*.sql")):
        match=re.match(r"^(\d+)_",p.name)
        if match: prefixes.setdefault(match.group(1),[]).append(p.name)
    for prefix,names in prefixes.items():
        if len(names)>1:
            notices.append(f"Migration prefix {prefix} is shared; verified lexical order: "+", ".join(names))
    result={"routes_checked":len(routes),"controller_views_checked":len(views),
            "route_targets":routes,"view_targets":views,
            "failures":failures,"notices":notices}
    (OUT/"static-consistency.json").write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding="utf-8")
    lines=["# Static Consistency Checks","",
           f"- Route targets checked: {len(routes)}",
           f"- Controller view references checked: {len(views)}",
           f"- Hard failures: {len(failures)}",""]
    lines += ["## Failures"] + ([f"- {x}" for x in failures] if failures else ["- None."])
    lines += ["","## Notices"] + ([f"- {x}" for x in notices] if notices else ["- None."])
    (OUT/"static-consistency.md").write_text("\n".join(lines)+"\n",encoding="utf-8")
    print(f"Static consistency: {len(routes)} route targets, {len(views)} controller views, {len(failures)} hard failures.")
    for issue in failures: print("ROUTE/VIEW ERROR: "+issue)
    for notice in notices: print("NOTICE: "+notice)
    return failures

def main():
    src=files()
    if not src: raise SystemExit("No PHP, JavaScript or SQL sources found in approved project paths.")
    print(f"Graphify source corpus: {len(src)} PHP/JS/SQL files; no .env, docs, uploads, storage, logs, dumps or vendor dependencies.")
    consistency_failures=consistency_checks()
    data=framework_edges(extract(src,cache_root=OUT,root=ROOT,parallel=True),src)
    failed=data.get("failed_sources",[])
    for item in failed:
        try: shown=Path(item).relative_to(ROOT)
        except ValueError: shown=item
        print(f"WARNING failed extraction: {shown}")
    (OUT/"source-extraction.json").write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding="utf-8")
    health=diagnose_extraction(data,directed=True,root=ROOT)
    (OUT/"graph-health.txt").write_text(format_diagnostic_report(health)+"\n",encoding="utf-8")
    print(format_diagnostic_report(health))
    G=build_from_json(data,root=ROOT,directed=True)
    if not G.number_of_nodes(): raise SystemExit("Graphify returned an empty graph; refusing an empty report.")
    communities=cluster(G); cohesion=score_all(G,communities)
    labels={}
    for cid,ids in communities.items():
        counts=Counter()
        for nid in ids:
            path=str(G.nodes[nid].get("source_file") or "")
            parts=Path(path).parts
            key=parts[1] if len(parts)>1 and parts[0]=="app" else (parts[0] if parts else "Mixed")
            counts[key]+=1
        name=counts.most_common(1)[0][0] if counts else "Mixed"
        labels[cid]=name.replace("_"," ").title()+" subsystem"
    gods=god_nodes(G); surprises=surprising_connections(G,communities)
    questions=suggest_questions(G,communities,labels)
    if not to_json(G,communities,str(OUT/"graph.json"),force=True,community_labels=labels):
        raise SystemExit("Graphify could not write graph.json.")
    total_words=sum(len(read(p).split()) for p in src)
    report=generate(G,communities,cohesion,labels,gods,surprises,
        {"total_files":len(src),"total_words":total_words,"files":{"code":[p.relative_to(ROOT).as_posix() for p in src]}},
        {"input":0,"output":0},str(ROOT),suggested_questions=questions)
    (OUT/"GRAPH_REPORT.md").write_text(report,encoding="utf-8")
    metadata={
        "source_commit":subprocess.check_output(["git","rev-parse","HEAD"],text=True).strip(),
        "files":[p.relative_to(ROOT).as_posix() for p in src],
        "node_count":G.number_of_nodes(),"edge_count":G.number_of_edges(),
        "community_count":len(communities),"community_labels":{str(k):v for k,v in labels.items()},
        "failed_sources":[str(p) for p in failed],"health":health,
        "static_consistency":json.loads((OUT/"static-consistency.json").read_text(encoding="utf-8")),
    }
    (OUT/"audit-metadata.json").write_text(json.dumps(metadata,ensure_ascii=False,indent=2),encoding="utf-8")
    print(f"Graphify complete: {G.number_of_nodes()} nodes, {G.number_of_edges()} edges, {len(communities)} communities.")
    if consistency_failures:
        raise SystemExit(f"Static consistency checks found {len(consistency_failures)} hard failure(s).")
    if failed:
        raise SystemExit(f"Graphify could not extract {len(failed)} source file(s); review graph-health.txt.")
if __name__=="__main__": main()
