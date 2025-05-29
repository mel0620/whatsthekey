<?php
// api/song-key.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

class SongKeyFinder {
    private $spotifyClientId;
    private $spotifyClientSecret;
    private $accessToken;
    
    public function __construct($clientId, $clientSecret) {
        $this->spotifyClientId = $clientId;
        $this->spotifyClientSecret = $clientSecret;
    }
    
    public function findSongKey($query) {
        // Extract song and artist from natural language query
        $songInfo = $this->parseQuery($query);
        if (!$songInfo) {
            return "I couldn't understand the song format. Please try: 'What's the key of [Song Title] by [Artist]?'";
        }
        
        $song = $songInfo['song'];
        $artist = $songInfo['artist'];
        
        // Try hybrid approach
        $key = $this->getKeyFromSpotify($song, $artist) ?? 
               $this->getKeyFromWebSearch($song, $artist);
        
        if ($key) {
            return "\"$song\" by $artist is in the key of $key";
        } else {
            return "Sorry, I couldn't find the key for \"$song\" by $artist online.";
        }
    }
    
    private function parseQuery($query) {
        // Match patterns like "key of Song by Artist" or "what's the key of Song by Artist"
        $patterns = [
            '/(?:what\'?s the )?key of (.+?) by (.+?)(?:\?|$)/i',
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
        
        return null;
    }
    
    private function getSpotifyToken() {
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
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;
        
        return $this->accessToken;
    }
    
    private function getKeyFromSpotify($song, $artist) {
        $token = $this->getSpotifyToken();
        if (!$token) return null;
        
        // Search for the song
        $searchQuery = urlencode("$song $artist");
        $searchUrl = "https://api.spotify.com/v1/search?q=$searchQuery&type=track&limit=1";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $searchData = json_decode($response, true);
        
        if (empty($searchData['tracks']['items'])) {
            return null;
        }
        
        $trackId = $searchData['tracks']['items'][0]['id'];
        
        // Get audio features
        $featuresUrl = "https://api.spotify.com/v1/audio-features/$trackId";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $featuresUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $features = json_decode($response, true);
        
        if (!isset($features['key']) || $features['key'] === -1) {
            return null;
        }
        
        // Convert key number to note name
        $keyMap = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
        $key = $keyMap[$features['key']];
        $mode = $features['mode'] == 1 ? 'Major' : 'Minor';
        
        return "$key $mode";
    }
    
    private function getKeyFromWebSearch($song, $artist) {
        // Try multiple search strategies
        $searchQueries = [
            "\"$song\" \"$artist\" \"key of\" OR \"in the key\"",
            "$song $artist key chord chart",
            "$song $artist ultimate guitar key"
        ];
        
        foreach ($searchQueries as $query) {
            $results = $this->searchWeb($query);
            $key = $this->extractKeyFromText($results);
            if ($key) return $key;
        }
        
        return null;
    }
    
    private function searchWeb($query) {
        // Using a simple web search - you might want to use Google Custom Search API
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $searchUrl = 'https://www.google.com/search?q=' . urlencode($query);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
    
    private function extractKeyFromText($text) {
        // Look for common key patterns in the text
        $patterns = [
            '/(?:key of |in the key |key: ?)([A-G][#b]?(?:\s?(?:major|minor|maj|min))?)/i',
            '/(?:capo|key)\s*:?\s*([A-G][#b]?)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $key = trim($matches[1]);
                // Normalize the key format
                $key = ucfirst(strtolower($key));
                $key = str_replace(['maj', 'min'], ['Major', 'Minor'], $key);
                return $key;
            }
        }
        
        return null;
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['error' => 'Please provide a query']);
        exit;
    }
    
    // You need to get Spotify API credentials from https://developer.spotify.com/
    $clientId = 'YOUR_SPOTIFY_CLIENT_ID';
    $clientSecret = 'YOUR_SPOTIFY_CLIENT_SECRET';
    
    $finder = new SongKeyFinder($clientId, $clientSecret);
    $response = $finder->findSongKey($query);
    
    echo json_encode(['response' => $response]);
} else {
    echo json_encode(['error' => 'Only POST requests allowed']);
}
?>