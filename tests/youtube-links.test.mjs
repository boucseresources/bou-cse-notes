import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const source=fs.readFileSync(new URL('../public/assets/youtube.js',import.meta.url),'utf8');
const {youtubeVideoLink,watchLinksFromText}=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
const id='dQw4w9WgXcQ';
test('accepts real YouTube video URL forms and preserves the video ID',()=>{
  for(const url of [`https://www.youtube.com/watch?v=${id}&list=PLignore`,`https://youtu.be/${id}`,`https://m.youtube.com/watch?v=${id}`,`https://www.youtube.com/shorts/${id}`,`https://www.youtube.com/live/${id}`,`https://www.youtube-nocookie.com/embed/${id}`,`https://www.yout-ube.com/watch?v=${id}`]) assert.equal(youtubeVideoLink(url)?.focus,`https://www.yout-ube.com/watch?v=${id}`);
});
test('rejects non-video links, lookalike hosts and unsafe input',()=>{
  for(const url of ['javascript:alert(1)',`https://youtube.com.evil.test/watch?v=${id}`,`https://evil.test/?v=${id}`,`https://youtube.com@evil.test/watch?v=${id}`,`https://user:pass@youtube.com/watch?v=${id}`,`https://youtube.com:8080/watch?v=${id}`,'https://youtube.com/playlist?list=PLexample','https://youtube.com/watch?v=too-short','not a URL']) assert.equal(youtubeVideoLink(url),null,url);
});
test('retains a valid timestamp without forwarding playlist or tracking parameters',()=>{
  assert.equal(youtubeVideoLink(`https://youtu.be/${id}?t=1h2m3s&si=secret`).focus,`https://www.yout-ube.com/watch?v=${id}&t=3723`);
  assert.equal(youtubeVideoLink(`https://youtube.com/embed/${id}?start=60`).focus,`https://www.yout-ube.com/watch?v=${id}&t=60`);
  assert.equal(youtubeVideoLink(`https://youtu.be/${id}?t=NaN`).focus,`https://www.yout-ube.com/watch?v=${id}`);
});
test('finds video links in plain announcements and deduplicates repeated video IDs',()=>{
  const html=watchLinksFromText(`Lesson: https://youtu.be/${id}. Again (https://www.youtube.com/watch?v=${id}) <script>alert(1)</script>`);
  assert.equal((html.match(/data-focus-video/g)||[]).length,1);
  assert.match(html,/rel="noopener noreferrer"/); assert.ok(!html.includes('<script>'));
  assert.equal(watchLinksFromText('No video here'), '');
});
