# Closed Captions Feasibility Analysis for Comlink Telehealth Module

## Executive Summary

**YES, it is possible to add closed captions to the telehealth module**, but with important considerations regarding implementation approach and dependencies.

## Current Architecture

### Video Conferencing Stack
- **Third-Party Service**: Comlink Video Bridge (CVB) - a proprietary WebRTC-based video conferencing service
- **Frontend**: Custom JavaScript implementation wrapping CVB SDK (`cvb.min.js`)
- **Communication**: WebRTC peer-to-peer connections for audio/video streams
- **Cost**: $16/month per provider for unlimited sessions

### Key Components
1. **TelehealthBridge** (`telehealth-bridge.js`) - Wrapper around CVB SDK
2. **ConferenceRoom** (`conference-room.js`) - Main conference room controller
3. **VideoBar** - Control panel for video/audio/screenshare
4. **CallerSlot** - Individual participant video slots
5. **PresentationScreen** - Screen sharing functionality

## Implementation Options

### Option 1: Browser-Based Web Speech API (Recommended for MVP)

**Description**: Use the browser's native Speech Recognition API to generate live captions client-side.

**Pros**:
- ✅ No additional cost or third-party dependencies
- ✅ Works entirely client-side (HIPAA friendly)
- ✅ Fast implementation (2-3 days)
- ✅ No server infrastructure changes needed
- ✅ Supported in modern Chrome, Edge, Safari

**Cons**:
- ❌ Limited browser support (Firefox lacks native support)
- ❌ Requires internet connection for some browsers
- ❌ Accuracy varies by browser and environment
- ❌ English-focused (limited multi-language support)
- ❌ Only captions the local user's audio stream

**HIPAA Considerations**: 
- Google's Speech API (used by Chrome) processes audio on Google servers
- May require BAA (Business Associate Agreement) with Google
- Safari uses Apple's on-device processing (more HIPAA-friendly)

**Implementation Steps**:
```javascript
// 1. Add caption toggle button to video-control-bar.twig
<button class="telehealth-btn-captions">
    <i class="fa fa-closed-captioning"></i>
</button>

// 2. Create new caption-manager.js
class CaptionManager {
    constructor() {
        this.recognition = new webkitSpeechRecognition();
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
    }
    
    start() {
        this.recognition.onresult = (event) => {
            // Display caption text in overlay
        };
        this.recognition.start();
    }
}

// 3. Add caption display overlay to conference-room.twig
<div class="caption-overlay">
    <div class="caption-text"></div>
</div>
```

**Estimated Effort**: 16-24 hours

---

### Option 2: Third-Party Transcription Service Integration

**Description**: Integrate a medical-grade transcription service (e.g., Deepgram, AssemblyAI, Azure Speech).

**Pros**:
- ✅ Professional accuracy (95%+)
- ✅ Medical terminology support
- ✅ Multi-language support
- ✅ Speaker diarization (identify who's speaking)
- ✅ Can caption all participants

**Cons**:
- ❌ Additional monthly costs ($0.0125-0.05 per minute)
- ❌ Requires server-side audio processing
- ❌ Need BAA for HIPAA compliance
- ❌ More complex implementation (1-2 weeks)
- ❌ Introduces latency (500ms-2s delay)

**HIPAA-Compliant Options**:
1. **Deepgram** - Medical model, BAA available, ~$0.0125/min
2. **Azure Speech Services** - HIPAA compliant, BAA available, ~$1/hour
3. **Amazon Transcribe Medical** - HIPAA compliant, $0.025/min

**Architecture Changes Required**:
```
Browser (WebRTC) → Audio Capture
    ↓
OpenEMR Server → Audio Stream Relay
    ↓
Transcription API → Text Stream
    ↓
WebSocket → All Participants (caption broadcast)
```

**Estimated Effort**: 40-80 hours

---

### Option 3: Comlink CVB SDK Extension (Requires Vendor Support)

**Description**: Request Comlink to add native caption support to their CVB SDK.

**Pros**:
- ✅ Seamlessly integrated with existing infrastructure
- ✅ Consistent across all participants
- ✅ Vendor handles HIPAA compliance
- ✅ Potentially included in existing subscription

**Cons**:
- ❌ Depends on vendor roadmap and priorities
- ❌ Timeline unknown (could be months)
- ❌ May incur additional fees
- ❌ No control over implementation

**Action Required**: Contact Comlink Inc to inquire about caption feature availability or development.

---

## Recommended Approach

### Phase 1: Basic Client-Side Captions (MVP)
**Timeline**: 2-3 days
**Cost**: Development time only

1. Implement Web Speech API for local user captions
2. Add caption toggle button to video controls
3. Create overlay UI for displaying captions
4. Add user preferences (font size, background opacity)
5. Implement browser compatibility detection

### Phase 2: Enhanced Captions (If Phase 1 is successful)
**Timeline**: 2-4 weeks
**Cost**: ~$50-200/month (depends on usage)

1. Evaluate HIPAA-compliant transcription services
2. Implement server-side audio processing
3. Add WebSocket support for real-time caption broadcast
4. Implement speaker identification
5. Add caption export/save functionality for medical records

### Phase 3: Medical Documentation Integration
**Timeline**: 1-2 weeks
**Cost**: Development time only

1. Auto-generate encounter notes from captions
2. Integrate with OpenEMR's existing documentation system
3. Add caption review/edit interface
4. Implement medical terminology autocorrect

---

## Technical Implementation Guide (Phase 1)

### 1. Add Caption Button to Video Controls

**File**: `templates/comlink/video-control-bar.twig`

```twig
{# Add after microphone button #}
<button type="button" class="btn btn-lg btn-default telehealth-btn telehealth-btn-captions d-none" 
        aria-label="{{ 'Toggle Captions'|xla }}" 
        title="{{ 'Toggle Captions'|xla }}">
    <i class="fa fa-closed-captioning fa-lg text-light" data-enabled="false"></i>
</button>
```

### 2. Create Caption Manager Module

**File**: `public/assets/js/src/caption-manager.js`

```javascript
/**
 * Manages live closed captioning for telehealth sessions
 * @package openemr
 * @copyright Copyright (c) 2026
 */
export class CaptionManager {
    constructor(containerElement, options = {}) {
        this.container = containerElement;
        this.options = {
            language: options.language || 'en-US',
            fontSize: options.fontSize || '1.2em',
            maxLength: options.maxLength || 200,
            displayDuration: options.displayDuration || 5000,
            ...options
        };
        
        this.recognition = null;
        this.isActive = false;
        this.captionOverlay = null;
        
        this.init();
    }
    
    init() {
        // Check browser support
        const SpeechRecognition = window.SpeechRecognition || 
                                   window.webkitSpeechRecognition;
        
        if (!SpeechRecognition) {
            console.warn('Speech Recognition not supported in this browser');
            return;
        }
        
        this.recognition = new SpeechRecognition();
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        this.recognition.lang = this.options.language;
        
        this.setupEventHandlers();
        this.createOverlay();
    }
    
    createOverlay() {
        this.captionOverlay = document.createElement('div');
        this.captionOverlay.className = 'telehealth-caption-overlay';
        this.captionOverlay.innerHTML = `
            <div class="caption-text" style="font-size: ${this.options.fontSize}"></div>
        `;
        this.container.appendChild(this.captionOverlay);
    }
    
    setupEventHandlers() {
        if (!this.recognition) return;
        
        this.recognition.onresult = (event) => {
            let interimTranscript = '';
            let finalTranscript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript + ' ';
                } else {
                    interimTranscript += transcript;
                }
            }
            
            this.displayCaption(finalTranscript || interimTranscript);
        };
        
        this.recognition.onerror = (event) => {
            console.error('Speech recognition error:', event.error);
            if (event.error === 'no-speech') {
                // Restart recognition after brief pause
                setTimeout(() => this.start(), 100);
            }
        };
        
        this.recognition.onend = () => {
            if (this.isActive) {
                // Restart if still supposed to be active
                this.recognition.start();
            }
        };
    }
    
    displayCaption(text) {
        if (!this.captionOverlay) return;
        
        const captionText = this.captionOverlay.querySelector('.caption-text');
        captionText.textContent = this.truncateText(text);
        
        // Show overlay
        this.captionOverlay.classList.add('active');
        
        // Auto-hide after duration
        clearTimeout(this.hideTimeout);
        this.hideTimeout = setTimeout(() => {
            this.captionOverlay.classList.remove('active');
        }, this.options.displayDuration);
    }
    
    truncateText(text) {
        if (text.length <= this.options.maxLength) {
            return text;
        }
        // Keep last N characters (most recent speech)
        return '...' + text.slice(-this.options.maxLength);
    }
    
    start() {
        if (!this.recognition) {
            alert('Closed captions are not supported in your browser. ' +
                  'Please use Chrome, Edge, or Safari.');
            return false;
        }
        
        try {
            this.recognition.start();
            this.isActive = true;
            return true;
        } catch (e) {
            console.error('Failed to start speech recognition:', e);
            return false;
        }
    }
    
    stop() {
        if (this.recognition && this.isActive) {
            this.isActive = false;
            this.recognition.stop();
            
            if (this.captionOverlay) {
                this.captionOverlay.classList.remove('active');
            }
        }
    }
    
    toggle() {
        if (this.isActive) {
            this.stop();
        } else {
            this.start();
        }
        return this.isActive;
    }
    
    isSupported() {
        return !!this.recognition;
    }
    
    destroy() {
        this.stop();
        if (this.captionOverlay) {
            this.captionOverlay.remove();
        }
    }
}
```

### 3. Add CSS Styling

**File**: `public/assets/css/telehealth.css`

```css
/* Closed Caption Overlay */
.telehealth-caption-overlay {
    position: absolute;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    max-width: 80%;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
    z-index: 1000;
}

.telehealth-caption-overlay.active {
    opacity: 1;
}

.telehealth-caption-overlay .caption-text {
    background-color: rgba(0, 0, 0, 0.8);
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 8px;
    font-family: Arial, sans-serif;
    font-size: 1.2em;
    line-height: 1.4;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    word-wrap: break-word;
}

/* Caption button active state */
.telehealth-btn-captions.active i {
    color: #28a745 !important;
}

/* Responsive sizing */
@media (max-width: 768px) {
    .telehealth-caption-overlay .caption-text {
        font-size: 1em;
        padding: 8px 16px;
    }
}
```

### 4. Integrate with Conference Room

**File**: `public/assets/js/src/conference-room.js`

```javascript
// Add to imports
import {CaptionManager} from "./caption-manager.js";

// Add to ConferenceRoom constructor
this.captionManager = null;
this.__captionsEnabled = false;

// Add method to initialize captions
this.initializeCaptions = function() {
    const conferenceContainer = this.roomNode.querySelector('.conference-room-container');
    if (!conferenceContainer) return;
    
    this.captionManager = new CaptionManager(conferenceContainer, {
        language: 'en-US',
        fontSize: '1.2em',
        maxLength: 200,
        displayDuration: 5000
    });
    
    // Setup caption button handler
    const captionBtn = this.roomNode.querySelector('.telehealth-btn-captions');
    if (captionBtn && this.captionManager.isSupported()) {
        captionBtn.classList.remove('d-none');
        captionBtn.addEventListener('click', () => {
            this.__captionsEnabled = this.captionManager.toggle();
            captionBtn.classList.toggle('active', this.__captionsEnabled);
        });
    }
};

// Call in appropriate lifecycle method
// Add to your session start handler
```

---

## HIPAA Compliance Considerations

### Web Speech API (Option 1)
- **Chrome/Edge**: Sends audio to Google servers - requires BAA with Google
- **Safari**: On-device processing (iOS 14.5+, macOS Big Sur+) - HIPAA-friendly
- **Firefox**: No native support - would need polyfill

### Recommended Mitigations:
1. Add clear user consent notice before enabling captions
2. Implement browser detection to prefer Safari when available
3. Document in privacy policy that caption feature uses browser APIs
4. Consider disabling for browsers that send data to third parties
5. Add admin setting to enable/disable caption feature

---

## Cost Analysis

### Option 1: Web Speech API
- Development: 16-24 hours (~$1,600-2,400 at $100/hr)
- Ongoing: $0/month
- **Total Year 1**: $1,600-2,400

### Option 2: Third-Party Service (Deepgram Medical)
- Development: 40-80 hours (~$4,000-8,000)
- API Cost: $0.0125/min × 60 min/session × 100 sessions/month = $75/month
- **Total Year 1**: $4,900-8,900

### Option 3: Vendor Extension
- Development: Unknown (vendor-dependent)
- Potential Fee: Unknown
- **Timeline**: Unknown

---

## Conclusion

**Recommendation**: Implement Phase 1 (Web Speech API) as a proof of concept. This provides:
- Fast time to market (1 week)
- Low cost
- Real user feedback
- Foundation for more sophisticated implementation

If Phase 1 proves valuable, proceed with Phase 2 for medical-grade transcription with proper HIPAA controls.

---

## Next Steps

1. **Immediate** (This Week):
   - Get stakeholder approval for Phase 1 approach
   - Review HIPAA implications with compliance team
   - Create feature branch for caption development

2. **Short Term** (1-2 Weeks):
   - Implement caption manager and UI
   - Test across browsers (Chrome, Safari, Edge)
   - Add user documentation

3. **Medium Term** (1-2 Months):
   - Gather user feedback
   - Evaluate need for Phase 2
   - Contact Comlink re: native caption support

4. **Long Term** (3-6 Months):
   - Evaluate medical documentation integration
   - Consider multi-language support
   - Explore AI-powered medical terminology recognition

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-02  
**Author**: Development Team  
**Status**: Feasibility Analysis Complete - Ready for Stakeholder Review
