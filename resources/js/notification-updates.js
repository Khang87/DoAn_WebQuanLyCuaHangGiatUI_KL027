const inbox = document.querySelector('[data-notification-updates]');
if (inbox) {
    let timer, controller, pending = false, stopped = false, disposed = false, delay = 15000, latestId;
    const bell = inbox.querySelector('button');
    const list = inbox.querySelector('[data-notification-list]');
    const count = inbox.querySelector('[data-notification-count]');
    const status = document.querySelector('[data-notification-status]');
    async function sync() {
        if (pending || stopped || disposed || document.hidden || !navigator.onLine) return;
        clearTimeout(timer); pending = true; controller = new AbortController();
        const deadline = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(inbox.dataset.notificationUpdates, {headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:controller.signal});
            if ([401,403].includes(response.status)) {
                stopped=true; list.replaceChildren(); count.hidden=true;
                bell.querySelector('.navbar-action-badge')?.remove();
                bell.setAttribute('aria-label','Thông báo không còn khả dụng');
                return;
            }
            if (!response.ok) throw Error('Notification sync failed');
            const data = await response.json();
            if (!Number.isSafeInteger(data.unread_count) || data.unread_count<0 || !Array.isArray(data.notifications) || data.notifications.length>5) throw Error('Invalid inbox');
            const fragment=document.createDocumentFragment();
            for (const item of data.notifications) {
                if (!Number.isSafeInteger(item.id) || typeof item.title!=='string' || typeof item.is_read!=='boolean' || typeof item.url!=='string') throw Error('Invalid notification');
                const url=new URL(item.url,location.origin);
                if (url.origin!==location.origin || url.pathname!==`/notifications/${item.id}`) throw Error('Invalid destination');
                const link=document.createElement('a'); link.href=url.href;
                link.className=`dropdown-item py-2 border-bottom notification-item ${item.is_read?'':'notification-item-unread'}`;
                const title=document.createElement('p');title.className='mb-1 small';title.textContent=item.title;
                const time=document.createElement('small');time.className='text-muted';time.textContent=item.time??'';
                link.append(title,time);fragment.append(link);
            }
            if (!data.notifications.length) {
                const empty=document.createElement('div');empty.className='p-3 text-center text-muted small';empty.textContent='Không có thông báo nào.';fragment.append(empty);
            }
            list.replaceChildren(fragment);count.hidden=data.unread_count===0;count.textContent=`${data.unread_count} mới`;
            bell.setAttribute('aria-label',`Thông báo, ${data.unread_count} tin chưa đọc`);
            if (data.unread_count>0 && !bell.querySelector('.navbar-action-badge')) {
                const badge=document.createElement('span');badge.className='navbar-action-badge';badge.setAttribute('aria-hidden','true');bell.append(badge);
            } else if (!data.unread_count) bell.querySelector('.navbar-action-badge')?.remove();
            const id=data.notifications[0]?.id??0;
            if (latestId!==undefined && id>latestId) status.textContent='Bạn có thông báo mới. Mở chuông thông báo để xem.';
            latestId=id;delay=15000;
        } catch { delay=Math.min(delay*2,120000); }
        finally {
            clearTimeout(deadline);pending=false;controller=null;
            if (!stopped && !disposed && !document.hidden && navigator.onLine) timer=setTimeout(sync,delay);
        }
    }
    function resume() {
        clearTimeout(timer);
        if (document.hidden || !navigator.onLine) controller?.abort();
        else void sync();
    }
    document.addEventListener('visibilitychange',resume);
    window.addEventListener('online',resume);window.addEventListener('offline',resume);
    window.addEventListener('pagehide',()=>{disposed=true;clearTimeout(timer);controller?.abort();});
    window.addEventListener('pageshow',event=>{if(event.persisted){disposed=false;resume();}});
    void sync();
}
