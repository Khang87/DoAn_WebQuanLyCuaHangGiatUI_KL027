const panel=document.querySelector('[data-booking-estimate]');
if(panel){
    const form=document.querySelector('#booking-edit-form');
    const total=panel.querySelector('[data-estimate-total]');
    const details=panel.querySelector('[data-estimate-details]');
    const warning=panel.querySelector('[data-estimate-warning]');
    const money=value=>new Intl.NumberFormat('vi-VN').format(value)+' VNĐ';
    let timer,controller,generation=0;
    function changed(){
        clearTimeout(timer);controller?.abort();const version=++generation;
        total.textContent='Đang tính…';details.textContent='';warning.textContent='';
        timer=setTimeout(async()=>{
            const requestController=new AbortController();controller=requestController;const deadline=setTimeout(()=>requestController.abort(),30000);
            try{
                const items=[...form.querySelectorAll('.booking-item')].map(row=>{
                    const item={};for(const field of row.querySelectorAll('[name^="items["]')){
                        if(field.disabled)continue;
                        const key=field.name.match(/\[([^\]]+)\]$/)?.[1];if(key)item[key]=field.value;
                    }return item;
                });
                const response=await fetch(panel.dataset.bookingEstimate,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({items,use_points:form.querySelector('[name="use_points"][type="checkbox"]')?.checked??false}),signal:requestController.signal});
                const data=await response.json();if(version!==generation)return;
                if(!response.ok)throw Error(response.status===422?'Chọn đủ dịch vụ, loại đồ, đơn vị có giá và nhập số lượng/khối lượng hợp lệ.':'Không thể tính ước tính. Vui lòng thử lại.');
                for(const key of ['TongTien','TienGiamKhuyenMai','TienGiamDoDiem','PhiGiaoHang','ThanhTien'])if(typeof data[key]!=='number'||!Number.isFinite(data[key])||data[key]<0)throw Error('Không thể đọc ước tính.');
                total.textContent=money(data.ThanhTien);
                details.textContent=`Tiền hàng: ${money(data.TongTien)} · Khuyến mãi: −${money(data.TienGiamKhuyenMai)} · Điểm: −${money(data.TienGiamDoDiem)} · Phí giao nhận: ${money(data.PhiGiaoHang)}`;
                warning.textContent=data.promotion_warning??'';
            }catch(error){if(version===generation){total.textContent='Chưa có ước tính';warning.textContent=error.name==='AbortError'?'Kết nối chậm. Thay đổi dữ liệu để tính lại.':error.message;}}
            finally{clearTimeout(deadline);}
        },350);
    }
    form.addEventListener('reset',()=>setTimeout(changed,0));
    form.addEventListener('input',changed);form.addEventListener('change',changed);
    const observer=new MutationObserver(changed);observer.observe(form.querySelector('#booking-items'),{childList:true});
    window.addEventListener('pagehide',()=>{clearTimeout(timer);generation++;controller?.abort();observer.disconnect();});
    window.addEventListener('pageshow',event=>{if(event.persisted){observer.observe(form.querySelector('#booking-items'),{childList:true});changed();}});
    changed();
}
