import {test} from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const source=await fs.readFile(new URL('../public/assets/session-client.js',import.meta.url),'utf8');
const {createSessionClient}=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));

function harness({user=null,token='fresh',transport}={}) {
    let localToken='stale', owner=user, serverToken=token;
    const calls=[], uploads=[];
    const response=(result,status=200,csrf=serverToken,userId=owner?.id??'')=>({ok:status>=200&&status<300,status,json:async()=>result,headers:new Headers({'X-CSRF-Token':csrf,'X-BOU-User':String(userId)})});
    const client=createSessionClient({
        readToken:()=>localToken, writeToken:t=>{localToken=t;},
        fetcher:async(url,opts)=>{
            const action=new URL(url,'https://in.aloskill.com').searchParams.get('action');calls.push({action,opts});
            if(transport){const custom=await transport({action,opts,response});if(custom)return custom;}
            if(action==='session')return response({csrf:serverToken,user:owner});
            if(opts.method==='POST'&&opts.headers['X-CSRF-Token']!==serverToken)return response({error:'Session expired.'},419);
            if(action==='logout'||action==='logout_all'){owner=null;serverToken='anonymous-new';return response({ok:true});}
            if(action==='login'||action==='register'){owner={id:7};serverToken='signed-in-new';return response({user:owner});}
            return response({ok:true});
        },
        uploader:async(action,form,csrf)=>{
            uploads.push({action,csrf,formToken:form.get('_csrf')});
            if(csrf!==serverToken)throw Object.assign(new Error('Expired'),{status:419});
            return {result:{ok:true},csrf:serverToken,user:String(owner?.id??'')};
        },
    });
    return {client,calls,uploads,token:()=>localToken,change:(u,t)=>{owner=u;serverToken=t;}};
}

test('boot session uses uncached same-origin credentials and adopts the current token',async()=>{
    const h=harness();await h.client.get('session');assert.equal(h.token(),'fresh');
    assert.equal(h.calls[0].opts.cache,'no-store');assert.equal(h.calls[0].opts.credentials,'same-origin');
});
for(const action of ['login','register','forgot','reset','verify','temp_create','temp_add','temp_delete']) {
    test(action+' recovers stale/expired anonymous sessions without losing submitted data',async()=>{
        const h=harness(),data={password:'unchanged-password',token:'email-token'};
        await h.client.request(action,data);
        assert.deepEqual(h.calls.map(x=>x.action),[action,'session',action]);
        assert.deepEqual(JSON.parse(h.calls[2].opts.body),data);
    });
}
for(const action of ['save','settings','password','email','resend','admin_config','announce','mark_read','study_save','coding_tick','share','report','admin_revoke']) {
    test(action+' renews a stale token once for the same signed-in account',async()=>{
        const h=harness({user:{id:7}});await h.client.get('session');h.change({id:7},'renewed');
        await h.client.request(action,{value:'preserved'});
        assert.deepEqual(h.calls.slice(1).map(x=>x.action),[action,'session',action]);
        assert.equal(h.calls.at(-1).opts.headers['X-CSRF-Token'],'renewed');
    });
}
for(const logout of ['logout','logout_all']) {
    test(logout+' immediately supplies a usable token for all guest forms',async()=>{
        const h=harness({user:{id:7}});await h.client.get('session');await h.client.request(logout,{});
        assert.equal(h.token(),'anonymous-new');
        await h.client.request('forgot',{});await h.client.request('reset',{});await h.client.request('login',{});
        assert.deepEqual(h.calls.map(x=>x.action),['session',logout,'forgot','reset','login']);
        assert.equal(h.token(),'signed-in-new');
    });
}
test('logout still works after the browser session has expired',async()=>{
    const h=harness({user:{id:7}});await h.client.get('session');h.change(null,'expired-new');
    await h.client.request('logout',{});assert.equal(h.token(),'anonymous-new');
});
for(const nextUser of [null,{id:8}]) {
    test('never retries a protected write into an expired or different account '+JSON.stringify(nextUser),async()=>{
        const h=harness({user:{id:7}});await h.client.get('session');h.change(nextUser,'new-account');
        await assert.rejects(h.client.request('save',{content:'private draft'}),e=>e.status===401);
        assert.deepEqual(h.calls.slice(1).map(x=>x.action),['save','session']);
        assert.equal(h.token(),'fresh');
    });
}
test('public recovery cannot unlock later private writes under another account',async()=>{
    const h=harness({user:{id:7}});await h.client.get('session');h.change({id:8},'account8');
    await h.client.request('temp_add',{text:'public drop'});const n=h.calls.length;
    await assert.rejects(h.client.request('save',{content:'private draft'}),e=>e.status===401);
    assert.equal(h.calls.length,n);
});
test('concurrent stale requests share one session refresh',async()=>{
    const h=harness();await Promise.all([h.client.request('forgot',{}),h.client.request('verify',{})]);
    assert.equal(h.calls.filter(x=>x.action==='session').length,1);
});
for(const action of ['upload','temp_add']) {
    test(action+' retries multipart with matching header and fallback tokens',async()=>{
        const h=harness({user:action==='upload'?{id:7}:null});await h.client.get('session');h.change(action==='upload'?{id:7}:null,'new-upload');
        const form=new FormData();form.set('_csrf','fresh');form.set('file',new Blob(['hello']),'hello.txt');
        await h.client.upload(action,form,()=>{});
        assert.deepEqual(h.uploads.map(x=>x.csrf),['fresh','new-upload']);
        assert.equal(h.uploads[1].formToken,'new-upload');assert.equal(form.get('file').name,'hello.txt');
    });
}
test('private upload never replays into another account',async()=>{
    const h=harness({user:{id:7}});await h.client.get('session');h.change({id:8},'account8');
    await assert.rejects(h.client.upload('upload',new FormData()),e=>e.status===401);assert.equal(h.uploads.length,1);
});
for(const status of [400,401,403,429,500]) {
    test('does not replay a '+status+' response',async()=>{
        const h=harness({transport:async({response})=>response({error:'Rejected'},status)});
        await assert.rejects(h.client.request('reset',{}),e=>e.status===status);assert.equal(h.calls.length,1);
    });
}
test('only one retry is allowed when sessions keep changing',async()=>{
    const h=harness({transport:async({action,response})=>action==='reset'?response({error:'Expired'},419):null});
    await assert.rejects(h.client.request('reset',{}),e=>e.status===419);assert.equal(h.calls.filter(x=>x.action==='reset').length,2);
});
test('no retry after a network failure that could have completed a write',async()=>{
    const h=harness({transport:async()=>{throw new Error('Network lost');}});
    await assert.rejects(h.client.request('save',{}),/Network lost/);assert.equal(h.calls.length,1);
});

test('a delayed read cannot overwrite the token returned by a newer login',async()=>{
    let release;
    const h=harness({transport:async({action,response})=>{
        if(action==='dashboard')return new Promise(resolve=>{release=()=>resolve(response({ok:true},200,'fresh',7));});
    },user:{id:7}});
    await h.client.get('session');const pending=h.client.get('dashboard');
    await h.client.request('login',{});release();await pending;
    assert.equal(h.token(),'signed-in-new');
});

test('a public response from before login cannot block the new authenticated session',async()=>{
    let release;
    const h=harness({transport:async({action,response})=>{
        if(action==='temp_get')return new Promise(resolve=>{release=()=>resolve(response({ok:true},200,'fresh',''));});
    }});
    await h.client.get('session');const pending=h.client.get('temp_get');
    await h.client.request('login',{});release();await pending;
    await h.client.request('save',{content:'new account draft'});
    assert.equal(h.token(),'signed-in-new');
});

test('protected reads reject a different account without accepting its token',async()=>{
    const h=harness({user:{id:7}});await h.client.get('session');h.change({id:8},'account8');
    await assert.rejects(h.client.get('notification_state'),e=>e.status===401);
    assert.equal(h.token(),'fresh');
});

test('the upload navigation lock covers renewal and is always released',async()=>{
    let lock=0,token='stale';
    const client=createSessionClient({readToken:()=>token,writeToken:t=>{token=t;},
        holdUpload:()=>{lock++;return ()=>lock--;},
        fetcher:async()=>{assert.equal(lock,1);return {ok:true,status:200,headers:new Headers(),json:async()=>({csrf:'fresh',user:null})};},
        uploader:async(action,form,csrf)=>{assert.equal(lock,1);if(csrf==='stale')throw Object.assign(new Error('Expired'),{status:419});return {result:{ok:true},user:'',csrf:'fresh'};},
    });
    await client.upload('temp_add',new FormData());assert.equal(lock,0);
});
