let pending=0,uploads=0,lastButton=null,lastActionAt=0;
export function holdUpload(){uploads++;let released=false;return ()=>{if(!released){released=true;uploads=Math.max(0,uploads-1)}}}
export const hasActiveUploads=()=>uploads>0;
document.addEventListener('click',e=>{lastButton=e.target.closest('button');lastActionAt=Date.now()},true);
document.addEventListener('submit',e=>{lastButton=e.submitter||e.target.querySelector('button:not([type=button])');lastActionAt=Date.now()},true);
export function beginFeedback(label='Loading',silent=false){
 if(silent)return ()=>{};
 let el=document.querySelector('#request-feedback');if(!el){el=document.createElement('div');el.id='request-feedback';el.innerHTML='<div class="request-line"></div><span class="request-pill" role="status"><i class="feedback-spinner"></i><span></span></span>';document.body.append(el)}pending++;el.hidden=false;el.querySelector('.request-pill>span').textContent=label+'…';
 const main=document.querySelector('#main');if(main&&!main.hasChildNodes())main.innerHTML='<div class="feedback-skeleton" aria-label="Loading page"><div></div><section><div></div><div></div><div></div></section></div>';
 const b=Date.now()-lastActionAt<300?lastButton:null,managed=b?.isConnected&&!b.classList.contains('request-busy');let wasDisabled=false;if(managed){wasDisabled=b.disabled;b.disabled=true;b.classList.add('request-busy');b.setAttribute('aria-busy','true')}
 return ()=>{pending=Math.max(0,pending-1);if(!pending)el.hidden=true;if(managed&&b.isConnected){b.disabled=wasDisabled;b.classList.remove('request-busy');b.removeAttribute('aria-busy')}};
}
export function uploadRequest(action,form,csrf,onProgress){return new Promise((resolve,reject)=>{const xhr=new XMLHttpRequest();uploads++;xhr.open('POST','api.php?action='+encodeURIComponent(action));xhr.withCredentials=true;xhr.setRequestHeader('X-CSRF-Token',csrf);xhr.timeout=300000;
 xhr.upload.onprogress=e=>onProgress?.(e.lengthComputable?e.loaded/e.total:null,'uploading');xhr.upload.onload=()=>onProgress?.(1,'processing');
 const finish=()=>{uploads=Math.max(0,uploads-1)};
 xhr.onload=()=>{finish();let data;try{data=JSON.parse(xhr.responseText)}catch{reject(new Error(xhr.status===413?'This file exceeds the hosting upload limit. Choose a smaller file.':'The upload server returned an unexpected response. Retry or check hosting upload limits.'));return}if(xhr.status<200||xhr.status>=300){reject(Object.assign(new Error(data.error||'Upload failed.'),{status:xhr.status}));return}onProgress?.(1,'complete');resolve({result:data,csrf:xhr.getResponseHeader('X-CSRF-Token'),user:xhr.getResponseHeader('X-BOU-User')})};
 xhr.onerror=()=>{finish();reject(new Error('Connection lost. Check your connection and retry the remaining files.'))};xhr.ontimeout=()=>{finish();reject(new Error('Upload timed out. Retry the remaining files.'))};xhr.onabort=()=>{finish();reject(new Error('Upload cancelled.'))};xhr.send(form);
 })}
export function progressMarkup(){return '<div class="upload-feedback" hidden><div class="upload-feedback-heading"><span class="feedback-spinner"></span><strong>Preparing upload</strong><span class="upload-percent">0%</span></div><div class="upload-track" role="progressbar" aria-label="File upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div></div></div><p class="upload-detail" role="status"></p><div class="upload-file-statuses"></div></div>'}
export function updateProgress(root,percent,title,detail,state='uploading'){
 root.hidden=false;root.dataset.state=state;root.querySelector('strong').textContent=title;const bar=root.querySelector('.upload-track');if(percent===null){bar.removeAttribute('aria-valuenow');root.classList.add('indeterminate');root.querySelector('.upload-percent').textContent='';}else{percent=Math.max(0,Math.min(100,Math.round(percent)));root.classList.remove('indeterminate');bar.setAttribute('aria-valuenow',percent);bar.firstElementChild.style.width=percent+'%';root.querySelector('.upload-percent').textContent=percent+'%'}root.querySelector('.upload-detail').textContent=detail;
}
export function connectionFeedback(){let el=document.querySelector('#connection-feedback');if(!el){el=document.createElement('div');el.id='connection-feedback';el.setAttribute('role','status');document.body.append(el)}el.hidden=navigator.onLine;el.textContent='You’re offline. Reconnect to save changes or upload files.'}
window.addEventListener('offline',connectionFeedback);window.addEventListener('online',connectionFeedback);connectionFeedback();


