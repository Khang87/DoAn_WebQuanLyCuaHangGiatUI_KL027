import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,mkdirSync,copyFileSync,existsSync} from 'node:fs';
import {openBrowser} from './browser.mjs';

test('213 browser signed avatar upload object verification persistence reload and foreign path denial with isolated Storage double',{timeout:120000},async()=>{
    const root=process.env.WEB_E2E_RUNTIME;
    writeFileSync(root+'/avatar-mock-enabled','Isolated Storage provider');
    const image=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aB1cAAAAASUVORK5CYII=','base64');
    writeFileSync(root+'/avatar-input.png',image);
    const runtime=root+'/avatar-browser';mkdirSync(runtime,{mode:0o700});copyFileSync(root+'/swal.js',runtime+'/swal.js');
    const uploaded=[];
    const b=await openBrowser(runtime,process.env.WEB_E2E_URL,{avatarStorage:async request=>{
        const url=new URL(request.url);
        const headers=[{name:'Access-Control-Allow-Origin',value:process.env.WEB_E2E_URL},{name:'Access-Control-Allow-Methods',value:'PUT,GET,OPTIONS'},{name:'Access-Control-Allow-Headers',value:'authorization,apikey,x-client-info,content-type,x-upsert'},{name:'Content-Type',value:'application/json'}];
        if(request.method==='OPTIONS')return {responseCode:200,responseHeaders:headers,body:''};
        if(request.method==='PUT'){
            assert.equal(url.pathname,'/storage/v1/object/upload/sign/avatars/avatars/2');
            assert.equal(url.searchParams.get('token'),'isolated-upload-token');
            uploaded.push(url.pathname);writeFileSync(root+'/avatar-object.png',image);
            return {responseCode:200,responseHeaders:headers,body:Buffer.from(JSON.stringify({Key:'avatars/avatars/2'})).toString('base64')};
        }
        assert.equal(request.method,'GET');assert.equal(url.pathname,'/storage/v1/object/public/avatars/avatars/2');
        assert.ok(existsSync(root+'/avatar-object.png'));
        return {responseCode:200,responseHeaders:[{name:'Content-Type',value:'image/png'}],body:readFileSync(root+'/avatar-object.png').toString('base64')};
    }});
    try{
        await b.navigate('/login');
        await b.evaluate(`document.querySelector('[name=email]').value='staff@example.test';document.querySelector('[name=password]').value=${JSON.stringify(process.env.WEB_E2E_PASSWORD)};document.querySelector('form').requestSubmit()`);
        await b.waitFor(async()=>await b.evaluate('location.pathname')==='/orders','login');
        await b.navigate('/profile');assert.equal(b.responses.filter(r=>r.type==='Document').at(-1).status,200);
        const doc=await b.command('DOM.getDocument');const input=await b.command('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'#avatarInput'});
        await b.command('DOM.setFileInputFiles',{nodeId:input.nodeId,files:[root+'/avatar-input.png']});
        await b.waitFor(async()=>await b.evaluate(`document.querySelector('#avatarPreview').src.startsWith('https://avatar-fixture.supabase.co/')`),'saved avatar');
        await b.waitFor(async()=>await b.evaluate(`document.querySelector('#avatarPreview').complete&&document.querySelector('#avatarPreview').naturalWidth===1`),'loaded 1x1 image');
        assert.equal(uploaded.length,1);
        const url=await b.evaluate(`document.querySelector('#avatarPreview').src`);
        await b.navigate('/profile');
        await b.waitFor(async()=>await b.evaluate(`document.querySelector('#avatarPreview').complete&&document.querySelector('#avatarPreview').naturalWidth===1`),'reloaded avatar');
        assert.equal(await b.evaluate(`document.querySelector('#avatarPreview').src`),url);
        const rejected=await b.evaluate(`(async()=>{const token=document.querySelector('meta[name="csrf-token"]').content;const r=await fetch('/profile/avatar',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({path:'avatars/3',content_type:'image/png'})});return {status:r.status,success:(await r.json()).success}})()`);
        assert.deepEqual(rejected,{status:422,success:false});
        await b.navigate('/profile');assert.equal(await b.evaluate(`document.querySelector('#avatarPreview').src`),url);
        assert.deepEqual(b.errors,[]);
    }finally{await b.close();}
});
