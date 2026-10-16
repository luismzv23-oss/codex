export const money = value => (Number(value)||0).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
export function totals(data) {
    let net=0,tax=0,extra=0;
    for(const item of data.items || []) {
        const base=Math.round((Number(item.quantity)||0)*(Number(item.unit_cost)||0)*100);
        net+=base;
        tax+=item.tax_amount!=='' && item.tax_amount!=null ? Math.round((Number(item.tax_amount)||0)*100) : Math.round(base*(Number(item.tax_rate)||0)/100);
    }
    for(const adjustment of data.adjustments || []) extra+=Math.round((Number(adjustment.amount)||0)*100)*(adjustment.kind==='discount'?-1:1);
    const total=net+tax+extra;
    const hasDocumentTotal=data.reviewed_total!=='' && data.reviewed_total!=null;
    return {net:net/100,tax:tax/100,extra:extra/100,total:total/100,difference:hasDocumentTotal?(total-Math.round(Number(data.reviewed_total)*100))/100:null};
}
export function mergeSuggestions(current,suggestions) {
    const result=structuredClone(current);
    for(const [key,value] of Object.entries(suggestions || {})) {
        if(key==='identity_reviewed') continue;
        if(result[key]===undefined || result[key]===null || result[key]==='' || (Array.isArray(result[key]) && result[key].length===0)) result[key]=structuredClone(value);
    }
    result.identity_reviewed=false;
    return result;
}
export function highlightRect(region,page) {
    if(!region || !page || !(page.width>0) || !(page.height>0) || !Array.isArray(region.polygon) || region.polygon.length<8 || region.polygon.some(value=>!Number.isFinite(Number(value)))) return null;
    const xs=region.polygon.filter((_,i)=>i%2===0).map(Number),ys=region.polygon.filter((_,i)=>i%2!==0).map(Number);
    const left=Math.max(0,Math.min(100,Math.min(...xs)/page.width*100)),top=Math.max(0,Math.min(100,Math.min(...ys)/page.height*100));
    return {left,top,width:Math.max(0,Math.min(100-left,(Math.max(...xs)-Math.min(...xs))/page.width*100)),height:Math.max(0,Math.min(100-top,(Math.max(...ys)-Math.min(...ys))/page.height*100))};
}
