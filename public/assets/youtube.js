// Generate links locally. Nothing is sent to the video service until a link is opened.
export function youtubeVideoLink(value) {
  let url;
  try { url = new URL(String(value).trim()); } catch { return null; }
  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.port) return null;
  const host = url.hostname.toLowerCase();
  let id;
  if (host === 'youtu.be' || host === 'www.youtu.be') id = url.pathname.slice(1);
  else if (['youtube.com','www.youtube.com','m.youtube.com','music.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com','yout-ube.com','www.yout-ube.com'].includes(host)) {
    if (url.pathname === '/watch') id = url.searchParams.get('v');
    else id = url.pathname.match(/^\/(?:embed|shorts|live)\/([^/]+)\/?$/)?.[1];
  }
  if (!/^[A-Za-z0-9_-]{11}$/.test(id || '')) return null;
  const focus = new URL('https://www.yout-ube.com/watch');
  const original = new URL('https://www.youtube.com/watch');
  focus.searchParams.set('v', id); original.searchParams.set('v', id);
  const rawTime = url.searchParams.get('t') || url.searchParams.get('start') || url.hash.match(/^#t=(.+)$/)?.[1];
  let seconds = 0;
  if (/^\d+$/.test(rawTime || '')) seconds = Number(rawTime);
  else {
    const time = (rawTime || '').match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);
    if (time) seconds = Number(time[1] || 0)*3600 + Number(time[2] || 0)*60 + Number(time[3] || 0);
  }
  if (Number.isSafeInteger(seconds) && seconds > 0 && seconds <= 604800) {
    focus.searchParams.set('t', String(seconds)); original.searchParams.set('t', String(seconds));
  }
  return {id, focus:focus.href, original:original.href};
}

export function watchLinksFromText(text) {
  const links = new Map();
  for (const match of String(text || '').matchAll(/https?:\/\/[^\s<>"']+/gi)) {
    const video = youtubeVideoLink(match[0].replace(/[).,;!?\]}]+$/, ''));
    if (video) links.set(video.id, video);
  }
  if (!links.size) return '';
  return `<div class="focus-video-links">${[...links.values()].map(v=>`<a class="btn small soft" href="${v.focus.replaceAll('&','&amp;')}" target="_blank" rel="noopener noreferrer" data-focus-video>Watch without distraction${links.size>1?' · '+v.id:''}</a>`).join('')}</div>`;
}

export function enableYoutubeWatchOptions(root) {
  const seen = new WeakSet();
  const scan = () => {
    for (const link of root.querySelectorAll('a[href]')) {
      if (seen.has(link) || link.hasAttribute('data-focus-video') || link.closest('pre,code,[contenteditable="true"]')) continue;
      seen.add(link);
      const video = youtubeVideoLink(link.href);
      if (!video || link.hostname.endsWith('yout-ube.com')) continue;
      const option = document.createElement('a');
      option.href=video.focus; option.target='_blank'; option.rel='noopener noreferrer';
      option.className='focus-watch-option'; option.dataset.focusVideo='';
      option.textContent='Watch without distraction'; option.title='Open this video on yout-ube.com';
      link.after(option);
    }
  };
  const observer = new MutationObserver(scan);
  observer.observe(root, {childList:true, subtree:true}); scan();
  return () => observer.disconnect();
}
