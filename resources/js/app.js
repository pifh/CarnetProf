import { marked } from 'marked';

// Filament 4.11's Markdown editor expects the parser to exist globally, but
// its distributed browser bundle does not include it. Keep the parser local
// to CarnetProf so previews work without sending any content off the server.
window.marked = marked;
