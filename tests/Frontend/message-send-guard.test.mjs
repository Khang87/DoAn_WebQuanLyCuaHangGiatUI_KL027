import {test} from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
test('double submit is blocked until page returns, without changing draft',()=>{
    const events={},windowEvents={},button={textContent:'Gửi',disabled:false};
    const form={dataset:{},querySelector:()=>button,addEventListener:(n,fn)=>events[n]=fn,setAttribute(){},removeAttribute(){}};
    vm.runInNewContext(readFileSync(new URL('../../resources/js/message-send-guard.js',import.meta.url),'utf8'),{document:{querySelector:()=>form},window:{addEventListener:(n,fn)=>windowEvents[n]=fn}});
    let prevented=0;const event={preventDefault:()=>prevented++};
    events.submit(event);assert.equal(button.disabled,true);events.submit(event);assert.equal(prevented,1);
    windowEvents.pageshow();assert.equal(button.disabled,false);assert.equal(button.textContent,'Gửi');
    events.submit(event);assert.equal(prevented,1);
    form.dataset.accessLost='true';windowEvents.pageshow();assert.equal(button.disabled,true);
});
