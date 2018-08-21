# YouTube-Time-Range-Extractor-To-Mp3
YouTube Time Range Extractor To Mp3 helper class for the Laravel Framework

This helper class is inspired by youtube-dl (https://github.com/rg3/youtube-dl) and extends its functionality by downloading only specified range of YouTube video and converting it to MP3 file with sound quality compatible with Amazon Echo.

macOS users can install `youtube-dl` and `ffmpeg` dependencies with Homebrew

```
brew install youtube-dl
brew install ffmpeg
```

Install this package with composer

```
composer require norkunas/youtube-dl-php
```

Sample usage:

```
$extractor = new App\Services\YoutubeTimeRangeExtractorToMp3();
$extractor->setVideoLink('https://www.youtube.com/watch?v=HFPTmvvvl8U');
$videoData = $extractor->getVideoDuration();
```

In order to download specified part of the video (ex.: from 10 sec. to 15 sec.) and convert it to MP3, use the code below:

```
$extractor->download(10, 15);
```

The audio file will be stored in `/path_to_your_laravel_project/storage/app/wpudQ75m85uedZ6MHCP0fhI3N0Rmc0miuUHLIpwZ.mp3`.

You can inspect the output of this command for more details.
