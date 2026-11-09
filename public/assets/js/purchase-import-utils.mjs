export const money = value => (Number(value)||0).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
export function importDecimal(value) {
    const match=/^(\d+)(?:\.(\d+))?$/.exec(String(value));
    if(!match) return value;
    const fraction=(match[2] || '').padEnd(3,'0');
    const cents=BigInt(match[1])*100n+BigInt(fraction.slice(0,2))+(fraction[2]>='5'?1n:0n);
    return `${cents/100n}.${String(cents%100n).padStart(2,'0')}`;
}
export function normalizeImportItem(item) {
    const result={...item,tax_amount:''};
    for(const key of ['unit_cost','tax_rate']) if(result[key]!=null && result[key]!=='') result[key]=importDecimal(result[key]);
    return result;
}
/** Invoice line in stock units: packages x units per package, cost per unit with 4 decimals (same rule as the server). */
export function stockLine(item) {
    const packages=Number(item?.quantity)||0;
    const raw=item?.units_per_package;
    const units=raw===undefined || raw===null || raw==='' ? 1 : Number(raw);
    if(!(units>0)) return {packages,units:0,quantity:0,unitCost:0,valid:false};
    return {packages,units,quantity:Math.round(packages*units*100)/100,unitCost:Math.round((Number(item?.unit_cost)||0)/units*10000)/10000,valid:true};
}
/** Assistant step to resume: 1 Subir, 2 Leer, 3 Revisar, 4 Productos, 5 Orden, 6 Aprobación, 7 Recepción, 8 Factura. */
export function resumeStage(draft,circuit) {
    if(!draft) return 1;
    if(draft.review_status==='registered') return 8;
    const steps=circuit?.steps || {};
    if((draft.data?.purchase_kind || 'goods')!=='goods') return draft.purchase_order_id ? 8 : 3;
    if(steps.receipt) return 8;
    if(steps.approval) return 7;
    if(steps.order) return 6;
    return 3;
}
/** Whether the assistant can move forward from a step, and why not. */
export function stepGate(stage,kind,circuit) {
    if((kind || 'goods')!=='goods' || stage<5 || stage>7) return {ok:true};
    const steps=circuit?.steps || {};
    if(stage===5 && !steps.order) return {ok:false,reason:'Genera la orden de compra o vincula una existente para continuar.'};
    if(stage===6 && !steps.approval) return {ok:false,reason:'Aprueba la orden de compra para continuar.'};
    if(stage===7 && !steps.receipt) return {ok:false,reason:'Registra la recepción para ingresar la mercadería al stock.'};
    return {ok:true};
}
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
