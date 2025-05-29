// SongKeyAssistant.vue
<template>
  <div class="song-key-assistant">
    <div class="chat-container">
      <h2>🎵 AI Music Assistant</h2>
      <p class="subtitle">Ask me about the key of any song!</p>
      
      <div class="chat-messages" ref="messagesContainer">
        <div 
          v-for="(message, index) in messages" 
          :key="index" 
          :class="['message', message.type]"
        >
          <div class="message-content">
            {{ message.text }}
          </div>
          <div class="message-time">
            {{ formatTime(message.timestamp) }}
          </div>
        </div>
        
        <div v-if="isLoading" class="message ai">
          <div class="message-content">
            <div class="typing-indicator">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
        </div>
      </div>
      
      <div class="input-section">
        <div class="example-queries">
          <p>Try asking:</p>
          <button 
            v-for="example in exampleQueries" 
            :key="example"
            @click="askQuestion(example)"
            class="example-btn"
          >
            {{ example }}
          </button>
        </div>
        
        <div class="input-group">
          <input
            v-model="userInput"
            @keyup.enter="askQuestion()"
            @input="clearError"
            placeholder="What's the key of Amazing Grace?"
            class="chat-input"
            :disabled="isLoading"
          />
          <button 
            @click="askQuestion()" 
            :disabled="isLoading || !userInput.trim()"
            class="send-btn"
          >
            Send
          </button>
        </div>
        
        <div v-if="error" class="error-message">
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
          type: 'ai',
          text: 'Hi! I can help you find the key of any song. Just ask me something like "What\'s the key of How Great is Our God by Chris Tomlin?"',
          timestamp: new Date()
        }
      ],
      isLoading: false,
      error: '',
      exampleQueries: [
        "What's the key of Amazing Grace?",
        "Key of Oceans by Hillsong United?",
        "What's the key of How Great is Our God by Chris Tomlin?"
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
      
      this.userInput = '';
      this.isLoading = true;
      this.error = '';
      
      try {
        const response = await fetch('/api/song-key.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ query })
        });
        
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        // Add AI response
        this.messages.push({
          type: 'ai',
          text: data.response,
          timestamp: new Date()
        });
        
      } catch (error) {
        console.error('Error:', error);
        this.error = 'Sorry, something went wrong. Please try again.';
        
        // Add error message to chat
        this.messages.push({
          type: 'ai',
          text: 'Sorry, I encountered an error while searching for that song. Please try again.',
          timestamp: new Date()
        });
      } finally {
        this.isLoading = false;
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
      container.scrollTop = container.scrollHeight;
    }
  }
}
</script>

<style scoped>
.song-key-assistant {
  max-width: 600px;
  margin: 0 auto;
  padding: 20px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.chat-container {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  overflow: hidden;
}

h2 {
  text-align: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  margin: 0;
  padding: 20px;
}

.subtitle {
  text-align: center;
  color: #666;
  margin: 15px 0;
}

.chat-messages {
  height: 400px;
  overflow-y: auto;
  padding: 20px;
  background: #f8f9fa;
}

.message {
  margin-bottom: 15px;
  display: flex;
  flex-direction: column;
}

.message.user {
  align-items: flex-end;
}

.message.ai {
  align-items: flex-start;
}

.message-content {
  max-width: 80%;
  padding: 12px 16px;
  border-radius: 18px;
  word-wrap: break-word;
}

.message.user .message-content {
  background: #007bff;
  color: white;
}

.message.ai .message-content {
  background: white;
  border: 1px solid #e0e0e0;
  color: #333;
}

.message-time {
  font-size: 11px;
  color: #888;
  margin-top: 4px;
  padding: 0 8px;
}

.typing-indicator {
  display: flex;
  gap: 4px;
}

.typing-indicator span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #007bff;
  animation: typing 1.4s infinite ease-in-out;
}

.typing-indicator span:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-10px);
  }
}

.input-section {
  padding: 20px;
  background: white;
  border-top: 1px solid #e0e0e0;
}

.example-queries {
  margin-bottom: 15px;
}

.example-queries p {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #666;
}

.example-btn {
  background: #f1f3f4;
  border: none;
  padding: 6px 12px;
  margin: 2px 4px;
  border-radius: 15px;
  font-size: 12px;
  cursor: pointer;
  transition: background 0.2s;
}

.example-btn:hover {
  background: #e8eaed;
}

.input-group {
  display: flex;
  gap: 10px;
}

.chat-input {
  flex: 1;
  padding: 12px 16px;
  border: 1px solid #ddd;
  border-radius: 25px;
  outline: none;
  font-size: 14px;
}

.chat-input:focus {
  border-color: #007bff;
}

.send-btn {
  background: #007bff;
  color: white;
  border: none;
  padding: 12px 20px;
  border-radius: 25px;
  cursor: pointer;
  transition: background 0.2s;
}

.send-btn:hover:not(:disabled) {
  background: #0056b3;
}

.send-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.error-message {
  background: #f8d7da;
  color: #721c24;
  padding: 10px;
  border-radius: 8px;
  margin-top: 10px;
  font-size: 14px;
}
</style>