<?php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use YoutubeDl\Exception\ExecutableNotFoundException;

/**
 * https://github.com/rg3/youtube-dl/issues/622
 *
 * Class YouTubeTimeRangeExtractorToMp3
 * @package App\Services
 * @author Plamen Markov <plamen@lynxlake.org>
 */
class YoutubeTimeRangeExtractorToMp3
{
    const PROGRESS_PATTERN = '/size=\s+(?<size>\d+(?:\.\d+)?(?:K|M|G)i?B)\s+time=(?<time>\d{2}:\d{2}:\d{2}\.\d{2})\s+bitrate=\s+(?<bitrate>[0-9\.]+?(?:K|M|G)bits?\/s)\s+speed=(?<speed>\d+(?:\.\d+)x?)?/i';

    /** @var string|null $videoLink */
    private $videoLink;

    /** @var string|null $directVideoLink */
    private $directVideoLink;

    /** @var int $videoDuration */
    private $videoDuration;

    /**  @var string $downloadPath */
    protected $downloadPath;

    /** @var callable $debug */
    protected $debug;

    /** @var int $timeout */
    protected $timeout = 0;

    /** @var callable $progress */
    private $progress;

    /** @var array $videoData */
    private $videoData;

    /**
     * YouTubeVideoRangeExtractor constructor.
     * @param null|string $videoLink
     */
    public function __construct(string $videoLink = null)
    {
        if (null === (new ExecutableFinder())->find('youtube-dl')) {
            throw new ExecutableNotFoundException('"youtube-dl" executable was not found. Did you forgot to add it to environment variables?');
        }

        if (null === (new ExecutableFinder())->find('ffmpeg')) {
            throw new ExecutableNotFoundException('"ffmpeg" executable was not found. Did you forgot to add it to environment variables?');
        }

        $this->videoLink = $videoLink;
        $this->directVideoLink = null;
        $this->videoDuration = -1;
        $this->videoData = [];
		if(function_exists("storage_path")) {
			$this->setDownloadPath(storage_path('app'));
		} else {
			$debug = $this->debug;
			if (is_callable($debug)) {
				$debug("warning", "Larvel not installed.  Trying to run without it.");
			}
			$this->setDownloadPath(__DIR__);
		}
    }

    /**
     * @param null|string $videoLink
     */
    public function setVideoLink(string $videoLink): null
    {
        $this->videoLink = $videoLink;
    }

    /**
     * @param string $downloadPath Download path without trailing slash
     */
    public function setDownloadPath(string $downloadPath)
    {
        $this->downloadPath = rtrim($downloadPath, '/');
    }

    /**
     * @return string
     */
    public function getDownloadPath(): string
    {
        return $this->downloadPath;
    }

    public function debug(callable $debug): null
    {
        $this->debug = $debug;
    }

    public function setTimeout(int $timeout): null
    {
        $this->timeout = $timeout;
    }

    public function onProgress(callable $onProgress): null
    {
        $this->progress = $onProgress;
    }

    /**
     * @param int $startTime
     * @param int $endTime
     * @return array
     * @throws \Exception
     */
    public function download(int $startTime, int $endTime): array
    {
        $this->getVideoData();

        $audioFile = $this->downloadPath . '/' . str_random(40) . '.mp3';

        // ffmpeg -i $(youtube-dl -f 22 --get-url https://www.youtube.com/watch?v=G_4dYKDC5pQ) -ss 10 -to 15 -ac 2 -codec:a libmp3lame -b:a 48k -ar 16000 sample.mp3
        $process = new Process('ffmpeg -i $(youtube-dl -f 22 --get-url ' . $this->videoLink . ') -ss ' . $startTime . ' -to ' . $endTime . ' -ac 2 -codec:a libmp3lame -b:a 48k -ar 16000 ' . $audioFile);
        $process->setTimeout($this->timeout);

        try {
            $process->mustRun(function ($type, $buffer) {
                $debug = $this->debug;
                $progress = $this->progress;

                if (is_callable($debug)) {
                    $debug($type, $buffer);
                }

                if (is_callable($progress) && Process::OUT === $type && preg_match(self::PROGRESS_PATTERN, $buffer, $matches)) {
                    // size, time, bitrate, speed
                    $progress($matches);
                }
            });

        } catch (\Exception $ex) {
            throw $ex;
        }

        $this->videoData['local_file'] = $audioFile;

        return $this->videoData;
    }

    /**
     * @return null
     * @throws \Exception
     */
    public function getVideoDuration(): int
    {
        return (int)$this->getVideoData()['duration'];
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getDirectVideoLink(): string
    {
        return $this->getVideoData()['url'];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getVideoData(): array
    {
        if (count($this->videoData) <= 1) {
            // youtube-dl -f 22 --get-url https://www.youtube.com/watch?v=G_4dYKDC5pQ
            $process = new Process([
                'youtube-dl',
                '-f',
                '22',
                '--get-url',
                $this->videoLink,
                '--dump-json'
            ]);

            try {
                $process->mustRun();

                if (preg_match('/(\{.+\})/si', $process->getOutput(), $matches)) {
                    $this->videoData = $this->jsonDecode($matches[1]);
                }

                if (!isset($this->videoData['url'])) {
                    throw new \RuntimeException('Cannot get video data.');
                }

            } catch (ProcessFailedException $exception) {
                throw new ProcessFailedException($process);
            }
        }

        return $this->videoData;
    }

    /**
     * @param $data
     * @return array
     * @throws \Exception
     */
    private function jsonDecode($data): array
    {
        $decoded = json_decode($data, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception(sprintf('Response can\'t be decoded: %s.', $data));
        }

        return $decoded;
    }
}
