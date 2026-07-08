import './bootstrap';
import Alpine from 'alpinejs';

// Stores globaux
Alpine.store('app', {
    currentVideo: null,
    videos: [],
    loading: false,
});

// Composants Alpine
import './components/youtube-input';
import './components/video-card';
import './components/transcript-viewer';
import './components/summary-panel';
import './components/chat-interface';
import './components/admin-panel';

window.Alpine = Alpine;
Alpine.start();
