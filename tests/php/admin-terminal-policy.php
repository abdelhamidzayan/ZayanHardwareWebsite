<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/controllers/AdminTerminalController.php');
$view = file_get_contents($root . '/app/views/admin/terminal.php');
if (!is_string($controller) || !is_string($view)) { fwrite(STDERR, "Unable to read admin terminal source.\\n"); exit(1); }
$required = [
    "$"."this->request->input('commands')", 'preg_split(', 'count($commands)>8',
    'strlen($command)>300', 'RateLimiter::allowForUser(\'admin.diagnostic_terminal\',$id,10,60,30)',
    'in_array($cmd,[\'sudo\',\'su\'],true)', 'proc_open($args,', "App::audit('admin.terminal.command'",
];
$missing = [];
foreach ($required as $marker) if (!str_contains($controller, $marker)) $missing[] = 'controller: ' . $marker;
foreach (['name="commands"','maxlength="1800"','rows="6"','10 مجموعات / الدقيقة','لا تكتب كلمة مرور SSH أو sudo هنا'] as $marker) if (!str_contains($view, $marker)) $missing[] = 'view: ' . $marker;
if (str_contains($controller, "proc_open(['bash'") || str_contains($controller, "proc_open(['sh'")) $missing[] = 'no shell interpreter';
if ($missing) { fwrite(STDERR, "Admin terminal policy check failed: " . implode('; ', $missing) . "\\n"); exit(1); }
echo "Admin terminal batch and safety policy check passed.\\n";
