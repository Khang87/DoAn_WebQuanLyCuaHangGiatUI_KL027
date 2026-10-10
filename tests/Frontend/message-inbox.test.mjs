import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../resources/js/message-inbox.js', import.meta.url), 'utf8');
function harness() {
    const element = () => ({dataset:{},style:{},children:[],classList:{add(){}},append(...children){this.children.push(...children);},replaceChildren(fragment){this.children=fragment?.children??[];},setAttribute(){}});
    const inbox=element(); inbox.dataset={messageInbox:'/inbox',messageLink:'/admin/messages',selectedOrder:'2'};
    const events={},timers=new Map(),calls=[]; let seq=0;
    const document={hidden:false,querySelector:()=>inbox,createElement:element,createDocumentFragment:element,addEventListener:(name,fn)=>events[name]=fn};
    const window={addEventListener:(name,fn)=>events[name]=fn};
    vm.runInNewContext(source,{document,window,navigator:{onLine:true},location:{origin:'https://test.local'},URL,AbortController,fetch:(url,options)=>new Promise((resolve,reject)=>{calls.push({resolve,options});options.signal.addEventListener('abort',()=>reject(Error('aborted')));}),setTimeout:(fn,ms)=>{timers.set(++seq,{fn,ms});return seq;},clearTimeout:id=>timers.delete(id)});
    const settle=async()=>{for(let i=0;i<12;i++) await Promise.resolve();};
    const reply=async(orders,status=200)=>{calls.at(-1).resolve({status,ok:status===200,json:async()=>({orders})});await settle();};
    return {inbox,events,timers,calls,document,settle,reply};
}
const order=(id,unread)=>({id,number:`DH${id}`,customer:'<img onerror=alert(1)>',date:'10-10-2026',unread});
test('latest order moves first, unread dot clears and text stays escaped without replacing document',async()=>{
    const h=harness();await h.reply([order(1,true),order(2,false)]);
    assert.equal(h.inbox.children[0].dataset.inboxOrder,'1');
    assert.equal(h.inbox.children[0].children[0].children[0].children.length,1);
    assert.equal(h.inbox.children[0].children[1].textContent,order(1,true).customer);
    h.events['message-inbox-changed']();await h.reply([order(2,true),order(1,false)]);
    assert.equal(h.inbox.children[0].dataset.inboxOrder,'2');
    assert.equal(h.inbox.children[1].children[0].children[0].children.length,0);
    assert.equal(h.inbox.children[0].href,'https://test.local/admin/messages?order_id=2');
});
test('hidden tabs pause and permission loss clears inbox and stops refresh',async()=>{
    const h=harness();await h.reply([order(1,true)]);
    h.document.hidden=true;h.events.visibilitychange();assert.equal(h.timers.size,0);
    h.document.hidden=false;h.events.visibilitychange();await h.reply([],403);
    assert.equal(h.inbox.children.length,0);assert.equal(h.timers.size,0);
    h.events.online();assert.equal(h.calls.length,2);
});
