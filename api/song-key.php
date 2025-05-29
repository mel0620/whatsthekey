<?php
// hybrid-song-key.php - TRUE HYBRID VERSION with Spotify + Fallback Database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// SPOTIFY API CREDENTIALS - Replace with your actual credentials
$SPOTIFY_CLIENT_ID = '308aa40eabf5493ea52e30ac18f4e0ff';
$SPOTIFY_CLIENT_SECRET = '812026c587b44506b556918e236085c2';

// Get query from either GET or POST
$query = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? $_GET['query'] ?? '';
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $input = json_decode($rawInput, true);
        $query = $input['query'] ?? '';
    }
}

// Validate input
if (empty($query)) {
    echo json_encode(['error' => 'Please provide a query']);
    exit;
}

// HYBRID SONG KEY FINDER CLASS
class HybridSongKeyFinder {
    private $spotifyClientId;
    private $spotifyClientSecret;
    private $accessToken;
    private $fallbackDatabase;
    
    public function __construct($clientId, $clientSecret) {
        $this->spotifyClientId = $clientId;
        $this->spotifyClientSecret = $clientSecret;
        $this->initializeFallbackDatabase();
    }
    
    private function initializeFallbackDatabase() {
        // Fallback database for when Spotify fails
        $this->fallbackDatabase = [
            // Worship songs
            'how great is our god' => ['key' => 'G Major', 'artist' => 'Chris Tomlin'],
            'amazing grace' => ['key' => 'G Major', 'artist' => 'Traditional'],
            'oceans' => ['key' => 'D Major', 'artist' => 'Hillsong United'],
            'way maker' => ['key' => 'E Major', 'artist' => 'Sinach'],
            'goodness of god' => ['key' => 'C Major', 'artist' => 'Bethel Music'],
            'reckless love' => ['key' => 'C Major', 'artist' => 'Cory Asbury'],
            'cornerstone' => ['key' => 'C Major', 'artist' => 'Hillsong Live'],
            'blessed be your name' => ['key' => 'B Major', 'artist' => 'Matt Redman'],
            'great are you lord' => ['key' => 'G Major', 'artist' => 'All Sons & Daughters'],
            '10000 reasons' => ['key' => 'G Major', 'artist' => 'Matt Redman'],
            'what a beautiful name' => ['key' => 'D Major', 'artist' => 'Hillsong Worship'],
            'build my life' => ['key' => 'G Major', 'artist' => 'Pat Barrett'],
            
            // Popular songs  
            'let it be' => ['key' => 'C Major', 'artist' => 'The Beatles'],
            'yesterday' => ['key' => 'F Major', 'artist' => 'The Beatles'],
            'hey jude' => ['key' => 'F Major', 'artist' => 'The Beatles'],
            'imagine' => ['key' => 'C Major', 'artist' => 'John Lennon'],
            'hallelujah' => ['key' => 'C Major', 'artist' => 'Leonard Cohen'],
            'wonderwall' => ['key' => 'G Major', 'artist' => 'Oasis'],
            'sweet child o mine' => ['key' => 'D Major', 'artist' => 'Guns N Roses'],
            'hotel california' => ['key' => 'B Minor', 'artist' => 'Eagles'],
        ];
    }
    
    public function findSongKey($query) {
        // Parse the query
        $parsed = $this->parseQuery($query);
        if (!$parsed) {
            return "I couldn't understand that format. Try: 'What's the key of [Song] by [Artist]?'";
        }
        
        $song = $parsed['song'];
        $artist = $parsed['artist'];
        
        // HYBRID APPROACH: Try Spotify first, then fallback database
        $result = $this->getKeyFromSpotify($song, $artist) ?? 
                 $this->getKeyFromFallbackDatabase($song, $artist);
        
        if ($result) {
            $source = $result['source'] ?? 'database';
            $keyInfo = $result['key'];
            $artistInfo = $result['artist'];
            
            if ($artist) {
                return "\"$song\" by $artist is in the key of $keyInfo (source: $source)";
            } else {
                return "\"$song\" by $artistInfo is in the key of $keyInfo (source: $source)";
            }
        } else {
            if ($artist) {
                return "Sorry, I couldn't find \"$song\" by $artist in either Spotify or my database.";
            } else {
                return "Sorry, I couldn't find \"$song\". Try including the artist name for better results.";
            }
        }
    }
    
    private function parseQuery($query) {
        $patterns = [
            '/(?:what\'?s the )?key of (.+?) by (.+?)(?:\?|$)/i',
            '/key of (.+?) by (.+?)(?:\?|$)/i',
            '/(.+?) by (.+?) key/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                return [
                    'song' => trim($matches[1]),
                    'artist' => trim($matches[2])
                ];
            }
        }
        
        // If no artist specified, try to match just the song
        if (preg_match('/(?:what\'?s the )?key of (.+?)(?:\?|$)/i', $query, $matches)) {
            return [
                'song' => trim($matches[1]),
                'artist' => null
            ];
        }
        
        return null;
    }
    
    private function getSpotifyToken() {
        // Skip if credentials not configured
        if ($this->spotifyClientId === '308aa40eabf5493ea52e30ac18f4e0ff' || 
            empty($this->spotifyClientId) || empty($this->spotifyClientSecret)) {
            return null;
        }
        
        if ($this->accessToken) return $this->accessToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->spotifyClientId . ':' . $this->spotifyClientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;
        return $this->accessToken;
    }
    
    private function getKeyFromSpotify($song, $artist) {
        $token = $this->getSpotifyToken();
        if (!$token) return null;
        
        // Search for the song
        $searchQuery = $artist ? "$song $artist" : $song;
        $searchUrl = 'https://api.spotify.com/v1/search?q=' . urlencode($searchQuery) . '&type=track&limit=1';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) return null;
        
        $searchData = json_decode($response, true);
        if (empty($searchData['tracks']['items'])) return null;
        
        $track = $searchData['tracks']['items'][0];
        $trackId = $track['id'];
        $trackName = $track['name'];
        $trackArtist = $track['artists'][0]['name'];
        
        // Get audio features for the key
        $featuresUrl = "https://api.spotify.com/v1/audio-features/$trackId";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $featuresUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $features = json_decode($response, true);
        if (!isset($features['key']) || $features['key'] === -1) return null;
        
        // Convert Spotify's key number to note name
        $keyMap = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
        $key = $keyMap[$features['key']];
        $mode = $features['mode'] == 1 ? 'Major' : 'Minor';
        
        return [
            'key' => "$key $mode",
            'artist' => $trackArtist,
            'source' => 'Spotify'
        ];
    }
    
    private function getKeyFromFallbackDatabase($song, $artist) {
        $songLower = strtolower($song);
        $artistLower = $artist ? strtolower($artist) : null;
        
        foreach ($this->fallbackDatabase as $dbSong => $info) {
            $dbSongLower = strtolower($dbSong);
            $dbArtistLower = strtolower($info['artist']);
            
            // Check if song matches
            $songMatch = (strpos($songLower, $dbSongLower) !== false || 
                         strpos($dbSongLower, $songLower) !== false);
            
            if ($songMatch) {
                // If artist is specified, check if it matches too
                if ($artistLower) {
                    $artistMatch = (strpos($artistLower, $dbArtistLower) !== false || 
                                   strpos($dbArtistLower, $artistLower) !== false);
                    if ($artistMatch) {
                        return array_merge($info, ['source' => 'local database']);
                    }
                } else {
                    // No artist specified, return first match
                    return array_merge($info, ['source' => 'local database']);
                }
            }
        }
        
        return null;
    }
}

try {
    // Create the hybrid finder
    $finder = new HybridSongKeyFinder($SPOTIFY_CLIENT_ID, $SPOTIFY_CLIENT_SECRET);
    $response = $finder->findSongKey($query);
    
    echo json_encode(['response' => $response]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'An error occurred while processing your request']);
}
?>