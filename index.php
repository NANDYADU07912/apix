<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/src/YouTubeDownloader.php';

$downloader = new YouTubeDownloader();

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/');

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function getParam($key) {
    $value = $_GET[$key] ?? $_POST[$key] ?? null;
    
    if (!$value) {
        $input = file_get_contents('php://input');
        $jsonData = json_decode($input, true);
        $value = $jsonData[$key] ?? null;
    }
    
    return $value;
}

switch ($path) {
    case '':
    case '/':
        jsonResponse([
            'success' => true,
            'message' => 'YouTube MP3 Download API',
            'version' => '1.0.0',
            'description' => 'Download songs from YouTube by name or URL in MP3 format',
            'endpoints' => [
                [
                    'path' => '/search',
                    'method' => 'GET/POST',
                    'description' => 'Search songs by name',
                    'parameters' => [
                        'song' => 'Song name to search (required)',
                        'limit' => 'Number of results (optional, default: 10, max: 20)'
                    ]
                ],
                [
                    'path' => '/info',
                    'method' => 'GET/POST',
                    'description' => 'Get song information by name or URL',
                    'parameters' => [
                        'song' => 'Song name or YouTube URL (required)'
                    ]
                ],
                [
                    'path' => '/download',
                    'method' => 'GET/POST',
                    'description' => 'Download song as MP3 by name or URL',
                    'parameters' => [
                        'song' => 'Song name or YouTube URL (required)'
                    ]
                ],
                [
                    'path' => '/clean',
                    'method' => 'POST',
                    'description' => 'Clean old downloaded files',
                    'parameters' => [
                        'hours' => 'Max age in hours (optional, default: 24)'
                    ]
                ]
            ],
            'example_usage' => [
                'search_by_name' => '/search?song=shape of you',
                'info_by_name' => '/info?song=believer imagine dragons',
                'info_by_url' => '/info?song=https://youtube.com/watch?v=VIDEO_ID',
                'download_by_name' => '/download?song=despacito',
                'download_by_url' => '/download?song=https://youtube.com/watch?v=VIDEO_ID'
            ]
        ]);
        break;
        
    case '/search':
        $song = getParam('song');
        
        if (!$song) {
            jsonResponse([
                'success' => false,
                'error' => 'Missing required parameter: song'
            ], 400);
        }
        
        $limit = (int) (getParam('limit') ?? 10);
        $limit = min(max($limit, 1), 20);
        
        $result = $downloader->searchSongs($song, $limit);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;
        
    case '/info':
        $song = getParam('song');
        
        if (!$song) {
            jsonResponse([
                'success' => false,
                'error' => 'Missing required parameter: song'
            ], 400);
        }
        
        $result = $downloader->getSongInfo($song);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;
        
    case '/download':
        $song = getParam('song');
        
        if (!$song) {
            jsonResponse([
                'success' => false,
                'error' => 'Missing required parameter: song'
            ], 400);
        }
        
        $result = $downloader->downloadMp3($song);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;
        
    case '/clean':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse([
                'success' => false,
                'error' => 'Method not allowed. Use POST.'
            ], 405);
        }
        
        $hours = (int) (getParam('hours') ?? 24);
        $deleted = $downloader->cleanOldFiles($hours);
        
        jsonResponse([
            'success' => true,
            'message' => "Cleaned {$deleted} old files"
        ]);
        break;
        
    default:
        if (strpos($path, '/downloads/') === 0) {
            $filename = basename($path);
            $filepath = __DIR__ . '/downloads/' . $filename;
            
            if (file_exists($filepath) && is_file($filepath)) {
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $mimeTypes = [
                    'mp3' => 'audio/mpeg'
                ];
                
                $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
                
                header('Content-Type: ' . $contentType);
                header('Content-Length: ' . filesize($filepath));
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Accept-Ranges: bytes');
                
                readfile($filepath);
                exit();
            }
            
            jsonResponse([
                'success' => false,
                'error' => 'File not found'
            ], 404);
        }
        
        jsonResponse([
            'success' => false,
            'error' => 'Endpoint not found'
        ], 404);
        break;
}
