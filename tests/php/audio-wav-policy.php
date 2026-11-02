<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/services/AudioWavPolicy.php';


function makeTestWav(int $sampleRate = 16000, int $channels = 1, int $bits = 16, int $durationMs = 1000): string
{
    $bytesPerSample = intdiv($bits, 8);
    $dataSize = (int) ($sampleRate * $durationMs / 1000) * $channels * $bytesPerSample;
    $blockAlign = $channels * $bytesPerSample;
    $fmt = pack('vvVVvv', 1, $channels, $sampleRate, $sampleRate * $blockAlign, $blockAlign, $bits);
    $data = str_repeat("\0", $dataSize);

    return 'RIFF' . pack('V', 36 + $dataSize) . 'WAVE'
        . 'fmt ' . pack('V', 16) . $fmt
        . 'data' . pack('V', $dataSize) . $data;
}

function expectRejected(string $bytes, string $label): void
{
    $path = tempnam(sys_get_temp_dir(), 'zayan-audio-invalid-');
    if ($path === false) throw new RuntimeException('Unable to create test file.');
    file_put_contents($path, $bytes);
    try {
        \\App\\Services\\AudioWavPolicy::inspect($path);
        throw new RuntimeException('Expected WAV rejection: ' . $label);
    } catch (DomainException) {
        // Expected validation failure.
    } finally {
        @unlink($path);
    }
}

$path = tempnam(sys_get_temp_dir(), 'zayan-audio-valid-');
if ($path === false) throw new RuntimeException('Unable to create test file.');
file_put_contents($path, makeTestWav());
try {
    $result = AudioWavPolicy::inspect($path);
    if ($result['mime_type'] !== 'audio/wav' || $result['duration_ms'] !== 1000 || $result['file_size'] < 32044) {
        throw new RuntimeException('Valid WAV metadata was not parsed correctly.');
    }
} finally {
    @unlink($path);
}

expectRejected('not-a-wave-file', 'invalid RIFF header');
expectRejected(makeTestWav(44100), 'wrong sample rate');
expectRejected(makeTestWav(16000, 2), 'stereo audio');
expectRejected(makeTestWav(16000, 1, 8), '8-bit PCM');

echo "Audio WAV policy accepts only valid mono 16 kHz PCM16 WAV files.\n";
