// api/spotifyAPI.js - JavaScript replacement for PHP backend
class SongKeyAPI {
    constructor() {
        // Replace these with your actual Spotify credentials
        this.SPOTIFY_CLIENT_ID = '308aa40eabf5493ea52e30ac18f4e0ff';
        this.SPOTIFY_CLIENT_SECRET = '812026c587b44506b556918e236085c2';
        this.accessToken = null;
        this.tokenExpiry = null;
        
        // Fallback song database
        this.songDatabase = {
            'how great is our god': { key: 'G Major', artist: 'Chris Tomlin' },
            'amazing grace': { key: 'G Major', artist: 'Traditional' },
            'oceans': { key: 'D Major', artist: 'Hillsong United' },
            'way maker': { key: 'E Major', artist: 'Sinach' },
            'goodness of god': { key: 'C Major', artist: 'Bethel Music' },
            'reckless love': { key: 'C Major', artist: 'Cory Asbury' },
            'cornerstone': { key: 'C Major', artist: 'Hillsong Live' },
            'blessed be your name': { key: 'B Major', artist: 'Matt Redman' },
            'great are you lord': { key: 'G Major', artist: 'All Sons & Daughters' },
            '10000 reasons': { key: 'G Major', artist: 'Matt Redman' },
            'what a beautiful name': { key: 'D Major', artist: 'Hillsong Worship' },
            'build my life': { key: 'G Major', artist: 'Pat Barrett' },
            'king of my heart': { key: 'C Major', artist: 'Bethel Music' },
            'lion and the lamb': { key: 'A Major', artist: 'Bethel Music' },
            
            // Popular songs
            'let it be': { key: 'C Major', artist: 'The Beatles' },
            'yesterday': { key: 'F Major', artist: 'The Beatles' },
            'hey jude': { key: 'F Major', artist: 'The Beatles' },
            'imagine': { key: 'C Major', artist: 'John Lennon' },
            'hallelujah': { key: 'C Major', artist: 'Leonard Cohen' },
            'wonderwall': { key: 'G Major', artist: 'Oasis' },
            'sweet child o mine': { key: 'D Major', artist: 'Guns N\' Roses' },
            'hotel california': { key: 'B Minor', artist: 'Eagles' },
            'stairway to heaven': { key: 'A Minor', artist: 'Led Zeppelin' },
            'bohemian rhapsody': { key: 'Bb Major', artist: 'Queen' },
            'shape of you': { key: 'C# Minor', artist: 'Ed Sheeran' },
            'perfect': { key: 'Ab Major', artist: 'Ed Sheeran' },
            'someone like you': { key: 'A Major', artist: 'Adele' },
            'rolling in the deep': { key: 'C Minor', artist: 'Adele' }
        };
    }
    
    // Get Spotify access token
    async getSpotifyToken() {
        // Return cached token if still valid
        if (this.accessToken && this.tokenExpiry && Date.now() < this.tokenExpiry) {
            return this.accessToken;
        }
        
        // Skip if credentials not set
        if (this.SPOTIFY_CLIENT_ID === '308aa40eabf5493ea52e30ac18f4e0ff' || 
            !this.SPOTIFY_CLIENT_ID || !this.SPOTIFY_CLIENT_SECRET) {
            return null;
        }
        
        try {
            const response = await fetch('https://accounts.spotify.com/api/token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Authorization': 'Basic ' + btoa(this.SPOTIFY_CLIENT_ID + ':' + this.SPOTIFY_CLIENT_SECRET)
                },
                body: 'grant_type=client_credentials'
            });
            
            if (!response.ok) {
                console.log('Failed to get Spotify token:', response.status);
                return null;
            }
            
            const data = await response.json();
            this.accessToken = data.access_token;
            this.tokenExpiry = Date.now() + (data.expires_in * 1000) - 60000; // 1 minute buffer
            
            return this.accessToken;
            
        } catch (error) {
            console.error('Spotify token error:', error);
            return null;
        }
    }
    
    // Get song key from Spotify
    async getKeyFromSpotify(song, artist) {
        try {
            const token = await this.getSpotifyToken();
            if (!token) return null;
            
            // Search for the song
            const searchQuery = artist ? `${song} ${artist}` : song;
            const searchUrl = `https://api.spotify.com/v1/search?q=${encodeURIComponent(searchQuery)}&type=track&limit=1`;
            
            const searchResponse = await fetch(searchUrl, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            if (!searchResponse.ok) return null;
            
            const searchData = await searchResponse.json();
            if (!searchData.tracks?.items?.length) return null;
            
            const track = searchData.tracks.items[0];
            const trackId = track.id;
            const trackName = track.name;
            const trackArtist = track.artists[0].name;
            
            // Get audio features for key
            const featuresUrl = `https://api.spotify.com/v1/audio-features/${trackId}`;
            const featuresResponse = await fetch(featuresUrl, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            if (!featuresResponse.ok) return null;
            
            const features = await featuresResponse.json();
            if (features.key === -1 || features.key === null) return null;
            
            // Convert key number to note name
            const keyMap = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
            const key = keyMap[features.key];
            const mode = features.mode === 1 ? 'Major' : 'Minor';
            
            return {
                key: `${key} ${mode}`,
                artist: trackArtist,
                song: trackName,
                source: 'Spotify'
            };
            
        } catch (error) {
            console.error('Spotify search error:', error);
            return null;
        }
    }
    
    // Get song key from local database
    getKeyFromDatabase(song, artist) {
        const songLower = song.toLowerCase();
        const artistLower = artist ? artist.toLowerCase() : null;
        
        for (const [dbSong, info] of Object.entries(this.songDatabase)) {
            const dbSongLower = dbSong.toLowerCase();
            const dbArtistLower = info.artist.toLowerCase();
            
            // Check if song matches
            const songMatch = songLower.includes(dbSongLower) || dbSongLower.includes(songLower);
            
            if (songMatch) {
                // If artist specified, check if it matches
                if (artistLower) {
                    const artistMatch = artistLower.includes(dbArtistLower) || dbArtistLower.includes(artistLower);
                    if (artistMatch) {
                        return {
                            key: info.key,
                            artist: info.artist,
                            song: song,
                            source: 'Local Database'
                        };
                    }
                } else {
                    // No artist specified, return first match
                    return {
                        key: info.key,
                        artist: info.artist,
                        song: song,
                        source: 'Local Database'
                    };
                }
            }
        }
        
        return null;
    }
    
    // Parse user query
    parseQuery(query) {
        const patterns = [
            /(?:what'?s the )?key of (.+?) by (.+?)(?:\?|$)/i,
            /key of (.+?) by (.+?)(?:\?|$)/i,
            /(.+?) by (.+?) key/i
        ];
        
        for (const pattern of patterns) {
            const match = query.match(pattern);
            if (match) {
                return {
                    song: match[1].trim(),
                    artist: match[2].trim()
                };
            }
        }
        
        // Try to match just the song
        const songPattern = /(?:what'?s the )?key of (.+?)(?:\?|$)/i;
        const songMatch = query.match(songPattern);
        if (songMatch) {
            return {
                song: songMatch[1].trim(),
                artist: null
            };
        }
        
        return null;
    }
    
    // Main method - hybrid approach
    async findSongKey(query) {
        try {
            // Parse the query
            const parsed = this.parseQuery(query);
            if (!parsed) {
                return "I couldn't understand that format. Try: 'What's the key of [Song] by [Artist]?'";
            }
            
            const { song, artist } = parsed;
            
            // Try Spotify first, then fallback to database
            let result = await this.getKeyFromSpotify(song, artist);
            if (!result) {
                result = this.getKeyFromDatabase(song, artist);
            }
            
            if (result) {
                if (artist) {
                    return `"${song}" by ${artist} is in the key of ${result.key} (source: ${result.source})`;
                } else {
                    return `"${song}" by ${result.artist} is in the key of ${result.key} (source: ${result.source})`;
                }
            } else {
                const suggestion = artist 
                    ? `Sorry, I couldn't find "${song}" by ${artist} in either Spotify or my database.`
                    : `Sorry, I couldn't find "${song}". Try including the artist name for better results.`;
                return suggestion;
            }
            
        } catch (error) {
            console.error('Song key search error:', error);
            return 'Sorry, an error occurred while searching for that song. Please try again.';
        }
    }
    
    // API endpoint simulation (to match your PHP structure)
    async handleRequest(query) {
        try {
            const response = await this.findSongKey(query);
            return { response: response };
        } catch (error) {
            return { error: 'An error occurred while processing your request' };
        }
    }
}

// Create global instance
window.songKeyAPI = new SongKeyAPI();

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SongKeyAPI;
}