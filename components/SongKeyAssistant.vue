<template>
  <div class="song-assistant">
    <div class="chat-container">
      <!-- Header -->
      <div class="header">
        <h1>🎵 Song Key Assistant</h1>
        <p>Ask me about the musical key of any song!</p>
      </div>

      <!-- Chat Messages -->
      <div class="messages" ref="messagesContainer">
        <div 
          v-for="(message, index) in messages" 
          :key="index"
          :class="['message', message.type]"
        >
          <div class="bubble">
            {{ message.text }}
          </div>
          <div class="timestamp">
            {{ formatTime(message.timestamp) }}
          </div>
        </div>

        <!-- Loading indicator -->
        <div v-if="loading" class="message assistant">
          <div class="bubble">
            <div class="typing">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Input Area -->
      <div class="input-area">
        <!-- Example buttons -->
        <div class="examples">
          <p>Try these examples:</p>
          <div class="example-buttons">
            <button 
              v-for="example in examples" 
              :key="example"
              @click="askQuestion(example)"
              class="example-btn"
              :disabled="loading"
            >
              {{ example }}
            </button>
          </div>
        </div>

        <!-- Input form -->
        <div class="input-form">
          <input
            v-model="userInput"
            @keyup.enter="askQuestion()"
            @input="clearError"
            placeholder="What's the key of Amazing Grace?"
            class="text-input"
            :disabled="loading"
          />
          <button 
            @click="askQuestion()" 
            :disabled="loading || !userInput.trim()"
            class="send-btn"
          >
            <span v-if="!loading">Send</span>
            <span v-else>...</span>
          </button>
        </div>

        <!-- Error message -->
        <div v-if="error" class="error">
          {{ error }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SongKeyAssistant',
  data() {
    return {
      userInput: '',
      messages: [
        {
          type: 'assistant',
          text: 'Hi! I can help you find the key of songs. Just ask me something like "What\'s the key of How Great is Our God by Chris Tomlin?" or "Key of Amazing Grace?"',
          timestamp: new Date()
        }
      ],
      loading: false,
      error: '',
      examples: [
        "What's the key of Amazing Grace?",
        "Key of Oceans by Hillsong United?",
        "What's the key of Let It Be by The Beatles?"
      ]
    }
  },
  methods: {
    async askQuestion(question = null) {
      const query = question || this.userInput.trim();
      
      if (!query) return;
      
      // Add user message
      this.messages.push({
        type: 'user',
        text: query,
        timestamp: new Date()
      });
      
      // Clear input and show loading
      this.userInput = '';
      this.loading = true;
      this.error = '';
      
      try {
        // Try GET method first (most compatible)
        const encodedQuery = encodeURIComponent(query);
        let response = await fetch(`./song-key.php?q=${encodedQuery}`, {
          method: 'GET'
        });
        
        // If GET fails, try POST
        if (!response.ok) {
          response = await fetch('./song-key.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({ query })
          });
        }
        
        if (!response.ok) {
          throw new Error(`Server error: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        // Add assistant response
        this.messages.push({
          type: 'assistant',
          text: data.response,
          timestamp: new Date()
        });
        
      } catch (error) {
        console.error('Error:', error);
        
        this.error = `Error: ${error.message}`;
        
        this.messages.push({
          type: 'assistant',
          text: 'Sorry, I encountered an error. Please check that the API is working and try again.',
          timestamp: new Date()
        });
      } finally {
        this.loading = false;
        this.$nextTick(() => {
          this.scrollToBottom();
        });
      }
    },
    
    clearError() {
      this.error = '';
    },
    
    formatTime(timestamp) {
      return timestamp.toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    },
    
    scrollToBottom() {
      const container = this.$refs.messagesContainer;
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    }
  }
}
</script>

<style scoped>
.song-assistant {
  max-width: 700px;
  margin: 20px auto;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.chat-container {
  background: white;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: 1px solid #e1e5e9;
}

.header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 24px;
  text-align: center;
}

.header h1 {
  margin: 0 0 8px 0;
  font-size: 28px;
  font-weight: 600;
}

.header p {
  margin: 0;
  opacity: 0.9;
  font-size: 16px;
}

.messages {
  height: 400px;
  overflow-y: auto;
  padding: 20px;
  background: #fafbfc;
}

.message {
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
}

.message.user {
  align-items: flex-end;
}

.message.assistant {
  align-items: flex-start;
}

.bubble {
  max-width: 85%;
  padding: 12px 16px;
  border-radius: 20px;
  word-wrap: break-word;
  line-height: 1.4;
}

.message.user .bubble {
  background: #007bff;
  color: white;
  border-bottom-right-radius: 6px;
}

.message.assistant .bubble {
  background: white;
  color: #333;
  border: 1px solid #e1e5e9;
  border-bottom-left-radius: 6px;
}

.timestamp {
  font-size: 12px;
  color: #8e9297;
  margin-top: 4px;
  margin-left: 12px;
  margin-right: 12px;
}

.typing {
  display: flex;
  gap: 4px;
  padding: 4px 0;
}

.typing span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #007bff;
  animation: typing 1.4s infinite ease-in-out;
}

.typing span:nth-child(2) { animation-delay: 0.2s; }
.typing span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-8px); }
}

.input-area {
  padding: 20px;
  background: white;
  border-top: 1px solid #e1e5e9;
}

.examples {
  margin-bottom: 16px;
}

.examples p {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #6c757d;
  font-weight: 500;
}

.example-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.example-btn {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  padding: 8px 12px;
  border-radius: 16px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
  color: #495057;
}

.example-btn:hover:not(:disabled) {
  background: #e9ecef;
  border-color: #adb5bd;
}

.example-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.input-form {
  display: flex;
  gap: 12px;
  align-items: center;
}

.text-input {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid #e1e5e9;
  border-radius: 24px;
  outline: none;
  font-size: 15px;
  transition: border-color 0.2s;
}

.text-input:focus {
  border-color: #007bff;
}

.text-input:disabled {
  background: #f8f9fa;
  color: #6c757d;
}

.send-btn {
  background: #007bff;
  color: white;
  border: none;
  padding: 12px 20px;
  border-radius: 24px;
  cursor: pointer;
  font-weight: 500;
  transition: background 0.2s;
  min-width: 70px;
}

.send-btn:hover:not(:disabled) {
  background: #0056b3;
}

.send-btn:disabled {
  background: #6c757d;
  cursor: not-allowed;
}

.error {
  background: #f8d7da;
  color: #721c24;
  padding: 12px 16px;
  border-radius: 8px;
  margin-top: 12px;
  font-size: 14px;
  border: 1px solid #f5c6cb;
}

/* Responsive */
@media (max-width: 600px) {
  .song-assistant {
    margin: 10px;
  }
  
  .example-buttons {
    flex-direction: column;
  }
  
  .example-btn {
    width: 100%;
  }
}
</style>