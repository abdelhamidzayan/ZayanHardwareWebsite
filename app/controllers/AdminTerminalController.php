<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\App;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Database;
use App\Services\AdminRosterService;
use App\Services\AuthService;
use App\Services\RateLimiter;

final class AdminTerminalController extends Controller
{
    public function index(): Response
    {
        if (!$this->admin()) return $this->redirect('/login');
        $result=$_SESSION['admin_terminal_result'] ?? null;
        unset($_SESSION['admin_terminal_result']);
        return $this->view('admin/terminal',['title'=>'وحدة التشخيص','active'=>'','terminalResult'=>is_array($result)?$result:null]);
    }
    public function run(): Response
    {
        $admin=$this->admin();
        if (!$admin) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) return $this->result(false,'رمز الحماية غير صالح.');
        $input=$this->request->input('command');
        if (!is_string($input)||trim($input)===''||strlen($input)>300) return $this->result(false,'اكتب أمرًا صحيحًا حتى 300 حرف.');
        $id=(int)$admin['id'];
        if (!RateLimiter::allowForUser('admin.diagnostic_terminal',$id,20,60,40)) return $this->result(false,'تجاوزت الحد: 20 أمرًا في الدقيقة.');
        try {
            AdminRosterService::assertActorActive(Database::connection(),$id);
            [$label,$output]=$this->execute(trim($input));
            App::audit('admin.terminal.command',['user_id'=>$id,'command'=>$label,'success'=>true]);
            return $this->result(true,$output);
        } catch (\DomainException $e) {
            return $this->result(false,'صلاحية المدير لم تعد نشطة.');
        } catch (\InvalidArgumentException $e) {
            App::audit('admin.terminal.command_rejected',['user_id'=>$id]);
            return $this->result(false,$e->getMessage());
        } catch (\Throwable $e) {
            App::logException('ERROR','admin.terminal.command_failed',$e,['user_id'=>$id]);
            return $this->result(false,'حدث خطأ. راجع سجل التطبيق.');
        }
    }
    private function execute(string $input): array
    {
        if (preg_match('/[;&|<>$(){}\x00-\x1F]/',$input)) throw new \InvalidArgumentException('رموز shell المركبة غير مسموحة.');
        $p=preg_split('/\s+/',$input) ?: []; $cmd=array_shift($p) ?? '';
        $root=realpath(App::root()); if (!$root) throw new \RuntimeException('Project root unavailable.');
        if ($cmd==='help'&&!$p) return ['help',"help\npwd\nls [path]\ncat <file>\nphp -v\nphp -l <file.php>\ngit status\ngit log\ngit diff --stat\nlogs [app|access|security|audit]\ndb status\ndb tables\ndb migration <filename.sql>\ndb preflight"];
        if ($cmd==='pwd'&&!$p) return ['pwd',$root];
        if ($cmd==='ls'&&count($p)<=1) {
            $d=$this->path($root,$p[0]??'.',true); $a=[];
            foreach(scandir($d)?:[] as $f) if($f!=='.'&&$f!=='..'&&!str_starts_with($f,'.')&&!in_array($f,['vendor','storage','uploads'],true)) $a[]=(is_dir($d.'/'.$f)?'[DIR]  ':'[FILE] ').$f;
            sort($a,SORT_NATURAL|SORT_FLAG_CASE); return ['ls',$a?implode("\n",$a):'(فارغ)'];
        }
        if ($cmd==='cat'&&count($p)===1) {
            $f=$this->path($root,$p[0],false); if(filesize($f)>65536) throw new \InvalidArgumentException('الملف أكبر من 64KB.');
            $v=file_get_contents($f); if(!is_string($v)||str_contains($v,"\0")) throw new \InvalidArgumentException('ملف ثنائي أو تعذرت قراءته.');
            return ['cat',$this->redact($v)];
        }
        if ($cmd==='php'&&$p===['-v']) return ['php -v',$this->process([PHP_BINARY,'-v'],$root)];
        if ($cmd==='php'&&count($p)===2&&$p[0]==='-l') {
            $f=$this->path($root,$p[1],false); if(strtolower(pathinfo($f,PATHINFO_EXTENSION))!=='php') throw new \InvalidArgumentException('حدد ملف PHP.');
            return ['php -l',$this->process([PHP_BINARY,'-l',$f],$root)];
        }
        if ($cmd==='git'&&$p===['status']) return ['git status',$this->process(['/usr/bin/git','-C',$root,'status','--short','--branch'],$root)];
        if ($cmd==='git'&&$p===['log']) return ['git log',$this->process(['/usr/bin/git','-C',$root,'log','-8','--oneline','--decorate'],$root)];
        if ($cmd==='git'&&$p===['diff','--stat']) return ['git diff --stat',$this->process(['/usr/bin/git','-C',$root,'diff','--stat'],$root)];
        if ($cmd==='db' && $p===['status']) return ['db status',$this->databaseStatus($root)];
        if ($cmd==='db' && $p===['preflight']) return ['db preflight',$this->databasePreflight($root)];
        if ($cmd==='db' && $p===['tables']) return ['db tables',$this->databaseTables()];
        if ($cmd==='db' && count($p)===2 && $p[0]==='migration') {
            $name=$p[1];
            if (!preg_match('/^\\d{3}_[a-z0-9_]+\\.sql$/D',$name)) throw new \InvalidArgumentException('حدد اسم ملف migration مرقمًا من مجلد database/migrations فقط.');
            $file=$this->path($root,'database/migrations/'.$name,false);
            if (filesize($file)>65536) throw new \InvalidArgumentException('ملف migration أكبر من 64KB.');
            $sql=file_get_contents($file);
            if (!is_string($sql)) throw new \RuntimeException('تعذرت قراءة migration.');
            return ['db migration',$this->redact($sql)];
        }
        if ($cmd==='logs'&&count($p)<=1) {
            $ch=$p[0]??'app'; if(!in_array($ch,['app','access','security','audit'],true)) throw new \InvalidArgumentException('القنوات: app / access / security / audit');
            $files=glob(App::root('storage/logs/'.$ch.'-*.log'))?:[]; rsort($files); if(!$files) return ['logs '.$ch,'لا توجد سجلات.'];
            $lines=file($files[0],FILE_IGNORE_NEW_LINES)?:[]; return ['logs '.$ch,$this->redact(implode("\n",array_slice($lines,-100)))];
        }
        throw new \InvalidArgumentException('الأمر غير مدعوم أو صياغته غير صحيحة. اكتب help.');
    }
    private function databaseStatus(string $root): string
    {
        $pdo=Database::connection();
        $meta=$pdo->query('SELECT DATABASE() AS db_name, VERSION() AS server_version')->fetch();
        $historical=glob($root.'/database/migrations/[0-9][0-9][0-9]_*.sql')?:[];
        $managed=glob($root.'/database/managed_migrations/[0-9][0-9][0-9]_*.sql')?:[];
        sort($historical,SORT_NATURAL);
        sort($managed,SORT_NATURAL);
        $lines=[
            'Database: '.(string)($meta['db_name']??'unknown'),
            'Server: '.(string)($meta['server_version']??'unknown'),
            'Historical SQL files (not an execution queue): '.count($historical),
            'Managed forward migration files: '.count($managed),
        ];

        $exists=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='app_migration_ledger' AND table_type='BASE TABLE'");
        $exists->execute();
        if((int)$exists->fetchColumn()===0) {
            $lines[]='Managed migration ledger: NOT FOUND.';
            $lines[]='Applied state: UNKNOWN — existing database adoption is blocked until a verified baseline is approved.';
            return implode("\n",$lines);
        }

        $columns=$pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_migration_ledger'")->fetchAll(\PDO::FETCH_COLUMN);
        $required=['migration_name','checksum','applied_at','execution_status','application_version','execution_duration_ms','failure_details','is_baseline','baseline_marker'];
        $missing=array_values(array_diff($required,$columns));
        if($missing) {
            $lines[]='BLOCKER — unsupported managed ledger; missing columns: '.implode(', ',$missing);
            return implode("\n",$lines);
        }

        $rows=$pdo->query("SELECT migration_name, checksum, applied_at, execution_status, application_version, execution_duration_ms, failure_details, is_baseline, baseline_marker FROM app_migration_ledger ORDER BY migration_name")->fetchAll();
        $byName=[];
        foreach($rows as $row) $byName[(string)$row['migration_name']]=$row;
        $baseline=$byName['__baseline_fresh_install_1_0_0__']??null;
        $expectedBaseline=hash('sha256','zayan-hardware:fresh-install:1.0.0');
        $baselineCount=count(array_filter($rows,static fn(array $row): bool => (int)$row['is_baseline']===1));
        $baselineValid=is_array($baseline)
            && $baselineCount===1
            && hash_equals($expectedBaseline,(string)$baseline['checksum'])
            && $baseline['execution_status']==='applied'
            && $baseline['application_version']==='1.0.0'
            && (int)$baseline['is_baseline']===1
            && $baseline['baseline_marker']==='fresh_install:1.0.0'
            && $baseline['applied_at']!==null;
        $lines[]=$baselineValid&&$baselineCount===1
            ? 'Fresh-install baseline: VERIFIED (1.0.0).'
            : 'BLOCKER — fresh-install baseline is missing, duplicated, or does not match the approved identity.';

        $seen=[];
        foreach($managed as $file) {
            $name=basename($file);
            $seen[$name]=true;
            $checksum=hash_file('sha256',$file);
            $row=$byName[$name]??null;
            if(!is_array($row)) {
                $lines[]='[NOT RECORDED] '.$name.' — not eligible for automatic execution.';
            } elseif(!is_string($checksum)||!hash_equals((string)$row['checksum'],$checksum)) {
                $lines[]='[CHECKSUM DRIFT] '.$name.' — file differs from its ledger checksum.';
            } elseif($row['execution_status']!=='applied') {
                $lines[]='[BLOCKED: '.strtoupper((string)$row['execution_status']).'] '.$name.' — manual recovery review required.';
            } else {
                $lines[]='[APPLIED] '.$name.' — '.(string)$row['applied_at'];
            }
        }

        foreach($rows as $row) {
            $name=(string)$row['migration_name'];
            if((int)$row['is_baseline']===1||isset($seen[$name])) continue;
            if(str_starts_with($name,'__baseline_')) continue;
            $lines[]='[ORPHAN LEDGER ROW] '.$name.' — ledger entry has no matching managed file.';
        }
        $lines[]='Ledger records: '.count($rows);
        $lines[]='Historical migrations 000–019 are informational only and are never auto-executed.';
        return implode("\n",$lines);
    }

    private function databasePreflight(string $root): string
    {
        $pdo=Database::connection();
        $meta=$pdo->query('SELECT DATABASE() AS db_name, VERSION() AS server_version')->fetch();
        $files=glob($root.'/database/migrations/[0-9][0-9][0-9]_*.sql')?:[];
        sort($files,SORT_NATURAL);
        $prefixes=[];
        $lines=[
            'Database: '.(string)($meta['db_name']??'unknown'),
            'Server: '.(string)($meta['server_version']??'unknown'),
            'Historical SQL files: '.count($files),
            'Execution mode: READ-ONLY PREFLIGHT — no SQL is executed.',
        ];
        foreach($files as $file) {
            if(preg_match('/^([0-9]{3})_/',basename($file),$m)) $prefixes[$m[1]][] = basename($file);
        }
        $duplicates=[];
        foreach($prefixes as $number=>$names) if(count($names)>1) $duplicates[]=$number.': '.implode(', ',$names);
        if($duplicates) {
            $lines[]='BLOCKER — duplicate migration sequence numbers:';
            foreach($duplicates as $duplicate) $lines[]='  '.$duplicate;
        } else {
            $lines[]='Sequence numbers: no duplicate three-digit prefixes found.';
        }

        $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='app_migration_ledger' AND table_type='BASE TABLE'");
        $q->execute();
        if((int)$q->fetchColumn()===0) {
            $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'");
            $q->execute();
            $count=(int)$q->fetchColumn();
            $lines[]='Managed migration ledger: NOT FOUND.';
            $lines[]='Applied history: UNKNOWN — never infer it from current schema or Git history.';
            $lines[]='Base tables currently present: '.$count;
            $lines[]=$count===0
                ? 'Empty database: use the separately reviewed fresh-install procedure.'
                : 'BLOCKER — existing database without a managed ledger. Prove its baseline before any migration.';
            $lines[]='Historical files 000–019 are not a safe automatic execution queue.';
            return implode("\n",$lines);
        }

        $columns=$pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_migration_ledger'")->fetchAll(\PDO::FETCH_COLUMN);
        $required=['migration_name','checksum','applied_at','execution_status','application_version','execution_duration_ms','failure_details','is_baseline','baseline_marker'];
        $missing=array_values(array_diff($required,$columns));
        if($missing) {
            $lines[]='BLOCKER — managed migration ledger has an unsupported schema; missing: '.implode(', ',$missing);
            return implode("\n",$lines);
        }

        $baseline=$pdo->query("SELECT migration_name, checksum, execution_status, application_version, is_baseline, baseline_marker, applied_at FROM app_migration_ledger WHERE is_baseline=1")->fetchAll();
        if(count($baseline)!==1) {
            $lines[]='BLOCKER — expected exactly one verified baseline marker; found '.count($baseline).'.';
            return implode("\n",$lines);
        }

        $marker=$baseline[0];
        $expectedChecksum=hash('sha256','zayan-hardware:fresh-install:1.0.0');
        $valid=$marker['migration_name']==='__baseline_fresh_install_1_0_0__'
            && hash_equals($expectedChecksum,(string)$marker['checksum'])
            && $marker['execution_status']==='applied'
            && $marker['application_version']==='1.0.0'
            && (int)$marker['is_baseline']===1
            && $marker['baseline_marker']==='fresh_install:1.0.0'
            && $marker['applied_at']!==null;
        if(!$valid) {
            $lines[]='BLOCKER — baseline marker does not match the approved fresh-install identity.';
            return implode("\n",$lines);
        }

        $summary=$pdo->query("SELECT COUNT(*) AS total, SUM(execution_status='failed') AS failed, SUM(execution_status='interrupted') AS interrupted, SUM(execution_status='applying') AS applying FROM app_migration_ledger")->fetch();
        $lines[]='Managed migration ledger: FOUND.';
        $lines[]='Fresh-install baseline: VERIFIED (1.0.0).';
        $lines[]='Ledger rows: '.(int)$summary['total'];
        $lines[]='Failed records: '.(int)$summary['failed'];
        $lines[]='Interrupted records: '.(int)$summary['interrupted'];
        $lines[]='Applying records: '.(int)$summary['applying'];
        if((int)$summary['failed']>0||(int)$summary['interrupted']>0||(int)$summary['applying']>0) {
            $lines[]='BLOCKER — unresolved migration execution state requires manual recovery review.';
        } else {
            $lines[]='No unresolved execution records were found.';
        }
        $lines[]='This command is read-only and does not authorize migration execution.';
        return implode("\n",$lines);
    }

    private function databaseTables(): string
    {
        $pdo=Database::connection();
        $rows=$pdo->query('SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY TABLE_NAME')->fetchAll();
        if(!$rows) return 'لا توجد جداول في قاعدة البيانات الحالية.';
        $lines=['Database: '.(string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'Tables/views: '.count($rows)];
        foreach($rows as $row) $lines[]='['.($row['TABLE_TYPE']==='VIEW'?'VIEW':'TABLE').'] '.$row['TABLE_NAME'];
        return implode("\n",$lines);
    }

    private function path(string $root,string $rel,bool $dir): string
    {
        if($rel===''||str_starts_with($rel,'/')||str_contains($rel,'\\')||str_contains($rel,'..')) throw new \InvalidArgumentException('المسار غير مسموح.');
        $f=realpath($root.'/'.$rel); if(!$f||($f!==$root&&!str_starts_with($f,$root.DIRECTORY_SEPARATOR))) throw new \InvalidArgumentException('المسار خارج المشروع أو غير موجود.');
        $parts=preg_split('~[\\\\/]~',substr($f,strlen($root)))?:[];
        if(array_intersect($parts,['.git','vendor','storage','uploads','cache'])) throw new \InvalidArgumentException('الوصول لهذا المجلد محظور.');
        $base=strtolower(basename($f));
        if($base==='.env'||in_array($base,['database.php','config.php','id_rsa'],true)||str_contains($base,'secret')||str_contains($base,'credential')||preg_match('/\.(pem|key|p12|pfx)$/i',$base)) throw new \InvalidArgumentException('ملف حساس: تم منع عرضه.');
        if($dir?!is_dir($f):!is_file($f)) throw new \InvalidArgumentException('نوع المسار غير صحيح.');
        return $f;
    }
    private function process(array $args,string $cwd): string
    {
        if(!function_exists('proc_open')) throw new \RuntimeException('proc_open غير متاح.');
        $pipes=[]; $p=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd,['PATH'=>'/usr/bin:/bin']);
        if(!is_resource($p)) throw new \RuntimeException('تعذر تشغيل أداة الفحص.');
        fclose($pipes[0]); $out=stream_get_contents($pipes[1])?:''; fclose($pipes[1]); $err=stream_get_contents($pipes[2])?:''; fclose($pipes[2]); $code=proc_close($p);
        return $this->redact(trim($out.($err!==''?"\n".$err:''))?:'Exit code: '.$code);
    }
    private function redact(string $s): string
    {
        $s=preg_replace('/(?im)^\s*(DB_PASSWORD|DB_PASS|DATABASE_PASSWORD|APP_KEY|APP_SECRET|SECRET_KEY|MAIL_PASSWORD|SMTP_PASSWORD|API_KEY|VAPID_PRIVATE_KEY)\s*=.*$/','$1=[REDACTED]',$s)??$s;
        $s=preg_replace('/(?i)(password|passwd|secret|token|api[_-]?key|private[_-]?key)(\s*[=:]\s*)([^\s,;]+)/','$1$2[REDACTED]',$s)??$s;
        return strlen($s)>30000?substr($s,0,30000)."\n[output truncated]":$s;
    }
    private function result(bool $ok,string $out): Response
    {
        $_SESSION['admin_terminal_result']=['success'=>$ok,'output'=>$out,'finishedAt'=>gmdate('Y-m-d H:i:s').' UTC'];
        return $this->redirect('/admin/terminal');
    }
    private function admin(): ?array
    {
        $u=AuthService::user(); return $u!==null&&($u['role']??'')==='admin'?$u:null;
    }
}
