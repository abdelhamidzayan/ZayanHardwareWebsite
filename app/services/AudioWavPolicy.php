<?php
declare(strict_types=1);

namespace App\Services;

final class AudioWavPolicy
{
    public const MAX_BYTES = 25 * 1024 * 1024;
    public const MAX_DURATION_MS = 15 * 60 * 1000;

    /**
     * Accept only a bounded mono 16 kHz PCM16 WAV produced by the browser trimmer.
     *
     * @return array{mime_type:string,file_size:int,duration_ms:int,sha256:string}
     */
    public static function inspect(string $path): array
    {
        $size = filesize($path);
        if (!is_int($size) || $size < 44 || $size > self::MAX_BYTES) {
            throw new \DomainException('audio_wav_size_invalid');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) throw new \DomainException('audio_wav_invalid');
        try {
            $header = fread($handle, 12);
            if (!is_string($header) || strlen($header) !== 12
                || substr($header, 0, 4) !== 'RIFF'
                || substr($header, 8, 4) !== 'WAVE') {
                throw new \DomainException('audio_wav_invalid');
            }
            $riffSize = unpack('Vsize', substr($header, 4, 4))['size'] ?? 0;
            if ($riffSize !== $size - 8) throw new \DomainException('audio_wav_invalid');

            $format = null;
            $dataSize = null;
            $position = 12;
            while ($position + 8 <= $size) {
                if (fseek($handle, $position) !== 0) throw new \DomainException('audio_wav_invalid');
                $chunkHeader = fread($handle, 8);
                if (!is_string($chunkHeader) || strlen($chunkHeader) !== 8) {
                    throw new \DomainException('audio_wav_invalid');
                }
                $chunkId = substr($chunkHeader, 0, 4);
                $chunkSize = unpack('Vsize', substr($chunkHeader, 4, 4))['size'] ?? 0;
                $chunkStart = $position + 8;
                if ($chunkSize < 0 || $chunkStart + $chunkSize > $size) {
                    throw new \DomainException('audio_wav_invalid');
                }

                if ($chunkId === 'fmt ') {
                    if ($chunkSize < 16 || fseek($handle, $chunkStart) !== 0) {
                        throw new \DomainException('audio_wav_invalid');
                    }
                    $fmt = fread($handle, 16);
                    if (!is_string($fmt) || strlen($fmt) !== 16) throw new \DomainException('audio_wav_invalid');
                    $values = unpack('vformat/vchannels/Vsample_rate/Vbyte_rate/vblock_align/vbits', $fmt);
                    $format = $values;
                } elseif ($chunkId === 'data') {
                    $dataSize = $chunkSize;
                }

                $position = $chunkStart + $chunkSize + ($chunkSize % 2);
            }

            if (!is_array($format) || $dataSize === null
                || (int) $format['format'] !== 1
                || (int) $format['channels'] !== 1
                || (int) $format['sample_rate'] !== 16000
                || (int) $format['byte_rate'] !== 32000
                || (int) $format['block_align'] !== 2
                || (int) $format['bits'] !== 16
                || $dataSize < 2 || ($dataSize % 2) !== 0) {
                throw new \DomainException('audio_wav_format_invalid');
            }

            $durationMs = (int) round(($dataSize / 32000) * 1000);
            if ($durationMs < 100 || $durationMs > self::MAX_DURATION_MS) {
                throw new \DomainException('audio_wav_duration_invalid');
            }
            $sha256 = hash_file('sha256', $path);
            if (!is_string($sha256)) throw new \DomainException('audio_wav_invalid');

            return [
                'mime_type' => 'audio/wav',
                'file_size' => $size,
                'duration_ms' => $durationMs,
                'sha256' => $sha256,
            ];
        } finally {
            fclose($handle);
        }
    }
}
