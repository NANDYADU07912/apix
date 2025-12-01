<?php

class YouTubeDownloader {
    private $downloadPath;
    private $baseUrl;
    
    public function __construct() {
        $this->downloadPath = __DIR__ . '/../downloads/';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:5000';
        $this->baseUrl = $protocol . '://' . $host . '/downloads/';
        
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0777, true);
        }
    }
    
    public function validateUrl($url) {
        $patterns = [
            '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/',
            '/^(https?:\/\/)?(www\.)?youtube\.com\/watch\?v=[\w-]+/',
            '/^(https?:\/\/)?youtu\.be\/[\w-]+/',
            '/^(https?:\/\/)?(www\.)?youtube\.com\/shorts\/[\w-]+/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        return false;
    }
    
    public function extractVideoId($url) {
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
    
    public function searchSongs($query, $limit = 10) {
        if (empty(trim($query))) {
            return [
                'success' => false,
                'error' => 'Search query cannot be empty'
            ];
        }
        
        $sanitizedQuery = preg_replace('/[^a-zA-Z0-9\s\-\'\"\.\,\(\)]/', '', $query);
        $searchTerm = escapeshellarg("ytsearch{$limit}:{$sanitizedQuery}");
        $command = "yt-dlp --cookies cookies.txt --dump-json --flat-playlist --no-playlist {$searchTerm} 2>&1";
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'error' => 'Failed to search songs',
                'details' => implode("\n", $output)
            ];
        }
        
        $results = [];
        foreach ($output as $line) {
            $videoData = json_decode($line, true);
            if ($videoData && isset($videoData['id'])) {
                $results[] = [
                    'id' => $videoData['id'],
                    'title' => $videoData['title'] ?? 'Unknown',
                    'url' => 'https://www.youtube.com/watch?v=' . $videoData['id'],
                    'duration' => $videoData['duration'] ?? 0,
                    'duration_string' => $this->formatDuration($videoData['duration'] ?? 0),
                    'channel' => $videoData['channel'] ?? $videoData['uploader'] ?? 'Unknown',
                    'thumbnail' => $videoData['thumbnails'][0]['url'] ?? null,
                    'view_count' => $videoData['view_count'] ?? 0
                ];
            }
        }
        
        return [
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'results' => $results
        ];
    }
    
    public function getSongInfo($identifier) {
        if (strlen($identifier) == 11 && preg_match('/^[a-zA-Z0-9_-]{11}$/', $identifier)) {
            $url = 'https://www.youtube.com/watch?v=' . $identifier;
        } elseif ($this->validateUrl($identifier)) {
            $url = $identifier;
        } else {
            $searchResult = $this->searchSongs($identifier, 1);
            if (!$searchResult['success'] || empty($searchResult['results'])) {
                return [
                    'success' => false,
                    'error' => 'Song not found'
                ];
            }
            $url = $searchResult['results'][0]['url'];
        }
        
        $escapedUrl = escapeshellarg($url);
        $command = "yt-dlp --cookies cookies.txt --dump-json --no-playlist --skip-unavailable-fragments {$escapedUrl} 2>&1";
        
        exec($command, $output, $returnCode);
        
        $jsonLine = '';
        foreach ($output as $line) {
            if (trim($line) === '') continue;
            if (strpos($line, 'WARNING') === 0) continue;
            if (strpos($line, '{') === 0) {
                $jsonLine = $line;
                break;
            }
        }
        
        if (!$jsonLine) {
            $jsonLine = end($output);
            if (!$jsonLine || strpos($jsonLine, '{') === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch song information',
                    'details' => implode("\n", $output)
                ];
            }
        }
        
        $videoData = json_decode($jsonLine, true);
        
        if (!$videoData) {
            return [
                'success' => false,
                'error' => 'Failed to parse song information',
                'details' => "Output: " . substr($jsonLine, 0, 100)
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'id' => $videoData['id'] ?? null,
                'title' => $videoData['title'] ?? 'Unknown',
                'description' => $videoData['description'] ?? '',
                'duration' => $videoData['duration'] ?? 0,
                'duration_string' => $this->formatDuration($videoData['duration'] ?? 0),
                'thumbnail' => $videoData['thumbnail'] ?? null,
                'channel' => $videoData['channel'] ?? $videoData['uploader'] ?? 'Unknown',
                'channel_url' => $videoData['channel_url'] ?? null,
                'view_count' => $videoData['view_count'] ?? 0,
                'upload_date' => $videoData['upload_date'] ?? null,
                'url' => 'https://www.youtube.com/watch?v=' . ($videoData['id'] ?? '')
            ]
        ];
    }
    
    private function formatDuration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }
    
    public function downloadMp3($identifier) {
        if (strlen($identifier) == 11 && preg_match('/^[a-zA-Z0-9_-]{11}$/', $identifier)) {
            $url = 'https://www.youtube.com/watch?v=' . $identifier;
            $videoId = $identifier;
        } elseif ($this->validateUrl($identifier)) {
            $url = $identifier;
            $videoId = $this->extractVideoId($identifier);
        } else {
            $searchResult = $this->searchSongs($identifier, 1);
            if (!$searchResult['success'] || empty($searchResult['results'])) {
                return [
                    'success' => false,
                    'error' => 'Song not found: ' . $identifier
                ];
            }
            $url = $searchResult['results'][0]['url'];
            $videoId = $searchResult['results'][0]['id'];
        }
        
        if (!$videoId) {
            return [
                'success' => false,
                'error' => 'Could not extract video ID'
            ];
        }
        
        $infoResult = $this->getSongInfo($url);
        $songTitle = $infoResult['success'] ? $infoResult['data']['title'] : $videoId;
        
        $filename = $videoId . '.mp3';
        $filepath = $this->downloadPath . $filename;
        
        if (file_exists($filepath)) {
            return [
                'success' => true,
                'data' => [
                    'title' => $songTitle,
                    'download_url' => $this->baseUrl . $filename,
                    'filename' => $filename,
                    'format' => 'mp3',
                    'quality' => '192kbps',
                    'file_size' => filesize($filepath),
                    'cached' => true
                ]
            ];
        }
        
        $escapedUrl = escapeshellarg($url);
        $escapedFilepath = escapeshellarg($filepath);
        $command = "yt-dlp --cookies cookies.txt -x --audio-format mp3 --audio-quality 192K -o {$escapedFilepath} --no-playlist {$escapedUrl} 2>&1";
        
        exec($command, $output, $returnCode);
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'Failed to download song',
                'details' => implode("\n", array_slice($output, -3))
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'title' => $songTitle,
                'download_url' => $this->baseUrl . $filename,
                'filename' => $filename,
                'format' => 'mp3',
                'quality' => '192kbps',
                'file_size' => filesize($filepath),
                'cached' => false
            ]
        ];
    }
    
    public function cleanOldFiles($maxAgeHours = 24) {
        $files = glob($this->downloadPath . '*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                $fileAge = (time() - filemtime($file)) / 3600;
                if ($fileAge > $maxAgeHours) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}
