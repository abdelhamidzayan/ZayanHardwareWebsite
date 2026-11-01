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
        if ($cmd==='help'&&!$p) return ['help',"help\npwd\nls [path]\ncat <file>\nphp -v\nphp -l <file.php>\ngit status\ngit log\ngit diff --stat\nlogs [app|access|security|audit]"];
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
        if ($cmd==='logs'&&count($p)<=1) {
            $ch=$p[0]??'app'; if(!in_array($ch,['app','access','security','audit'],true)) throw new \InvalidArgumentException('القنوات: app / access / security / audit');
            $files=glob(App::root('storage/logs/'.$ch.'-*.log'))?:[]; rsort($files); if(!$files) return ['logs '.$ch,'لا توجد سجلات.'];
            $lines=file($files[0],FILE_IGNORE_NEW_LINES)?:[]; return ['logs '.$ch,$this->redact(implode("\n",array_slice($lines,-100)))];
        }
        throw new \InvalidArgumentException('الأمر غير مدعوم أو صياغته غير صحيحة. اكتب help.');
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
