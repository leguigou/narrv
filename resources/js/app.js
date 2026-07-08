import './bootstrap';
import Alpine from 'alpinejs';

import adminPanel from './components/admin-panel';
import chatInterface from './components/chat-interface';
import mediaDownloader from './components/media-downloader';
import summaryPanel from './components/summary-panel';
import transcriptViewer from './components/transcript-viewer';
import videoCard from './components/video-card';
import youtubeInput from './components/youtube-input';

Alpine.store('app', {
    currentVideo: null,
    videos: [],
    loading: false,
});

Alpine.data('adminPanel', adminPanel);
Alpine.data('chatInterface', chatInterface);
Alpine.data('mediaDownloader', mediaDownloader);
Alpine.data('summaryPanel', summaryPanel);
Alpine.data('transcriptViewer', transcriptViewer);
Alpine.data('videoCard', videoCard);
Alpine.data('youtubeInput', youtubeInput);

window.Alpine = Alpine;
Alpine.start();
