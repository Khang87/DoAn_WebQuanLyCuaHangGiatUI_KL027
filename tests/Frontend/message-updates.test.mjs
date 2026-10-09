import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source=readFileSync(new URL('../../resources/js/message-updates.js',import.meta.url),'utf8');
function harness() {
    const timers=new Map(), events={}, documentEvents={}, calls=[];
    let clock=0;
    const element=()=>({dataset:{},style:{},children:[],append(child){this.children.push(child);},replaceChildren(fragment){this.children=fragment?.children||[];},scrollTop:0,scrollHeight:10,clientHeight:10});
    const conversation=element();conversation.dataset={orderId:'7',messageUpdates:'/updates'};
    const status={textContent:''}, input={value:'draft',disabled:false};
    const document={hidden:false,querySelector:s=>s==='[data-message-updates]'?conversation:s==='[data-message-status]'?status:{querySelectorAll:()=>[input]},createElement:element,createDocumentFragment:element,addEventListener:(name,fn)=>{documentEvents[name]=fn;}};
    const navigator={onLine:true};
    const context={document,navigator,window:{addEventListener:(name,fn)=>{events[name]=fn;}},AbortController,fetch:(url,options)=>new Promise((resolve,reject)=>{calls.push({resolve,reject,options});options.signal.addEventListener('abort',()=>reject(Error('aborted')));}),setTimeout:(fn,ms)=>{timers.set(++clock,{fn,ms});return clock;},clearTimeout:id=>timers.delete(id)};
    vm.runInNewContext(source,context);
    const settle=async()=>{for(let i=0;i<10;i++)await Promise.resolve();};
    const respond=async(statusCode=200,messages=[])=>{calls.at(-1).resolve({status:statusCode,ok:statusCode===200,json:async()=>({order_id:7,messages})});await settle();};
    const runTimer=()=>{const [id,timer]=[...timers].find(([,v])=>v.ms!==30000);timers.delete(id);timer.fn();return timer.ms;};
    return {conversation,status,input,document,navigator,events,documentEvents,calls,timers,settle,respond,runTimer};
}
const message={id:1,content:'<img onerror=alert(1)>',sender_name:'Customer',is_mine:false,sent_at:null};
test('snapshot renders text, preserves draft and avoids duplicate messages',async()=>{
    const h=harness();await h.respond(200,[message]);
    assert.equal(h.conversation.children[0].children[0].children[1].textContent,message.content);
    assert.equal(h.input.value,'draft');
    assert.equal(h.runTimer(),15000);await h.respond(200,[message]);
    assert.equal(h.conversation.children.length,1);
});
test('requests never overlap and pause while hidden, then resume',async()=>{
    const h=harness();h.events.online();assert.equal(h.calls.length,1);
    h.document.hidden=true;h.documentEvents.visibilitychange();await h.settle();
    assert.equal(h.timers.size,0);
    h.document.hidden=false;h.documentEvents.visibilitychange();assert.equal(h.calls.length,2);
    await h.respond();assert.equal(h.runTimer(),15000);
});
test('network failure backs off then successful recovery restores interval',async()=>{
    const h=harness();h.calls[0].reject(Error('offline'));await h.settle();
    // Retry delay is 30 seconds, as is the deadline (already cleared).
    const [id,timer]=[...h.timers][0];assert.equal(timer.ms,30000);h.timers.delete(id);timer.fn();
    await h.respond();assert.equal(h.runTimer(),15000);
});
for(const code of [401,403,404])test(`${code} clears protected content and stops requests`,async()=>{
    const h=harness();await h.respond(200,[message]);h.runTimer();await h.respond(code);
    assert.equal(h.conversation.children.length,0);assert.equal(h.input.disabled,true);assert.equal(h.timers.size,0);
    h.events.online();h.events.pagehide();h.events.pageshow({persisted:true});assert.equal(h.calls.length,2);
});
test('invalid or foreign-order response keeps last valid snapshot',async()=>{
    const h=harness();await h.respond(200,[message]);h.runTimer();
    h.calls.at(-1).resolve({status:200,ok:true,json:async()=>({order_id:8,messages:[]})});await h.settle();
    assert.equal(h.conversation.children.length,1);
});
test('reading older messages preserves scroll position; bottom view follows new snapshot',async()=>{
    const h=harness();h.conversation.scrollHeight=1000;h.conversation.clientHeight=100;h.conversation.scrollTop=200;
    await h.respond(200,[message]);assert.equal(h.conversation.scrollTop,200);
    h.conversation.scrollTop=900;h.runTimer();await h.respond(200,[message,{...message,id:2}]);
    assert.equal(h.conversation.scrollTop,1000);
});
