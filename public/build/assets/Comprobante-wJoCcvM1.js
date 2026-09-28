import{F as e,Mt as t,N as n,O as r,X as i,_ as a,at as o,c as s,d as c,dt as l,g as u,l as ee,r as d,s as f,u as p}from"./runtime-core.esm-bundler-C8qHIgUv.js";import{t as te}from"./AppLayout-CzouV1Ew.js";import{s as m}from"./overlayeventbus-CcMS_Jp8.js";import{kt as ne}from"./app-BVQR3YU7.js";import{t as h}from"./_plugin-vue_export-helper-BDNMzG2s.js";import{t as re}from"./Card-7SiiOnbi.js";var ie={class:`p-4 md:p-6 receipt-print-scope`},ae={class:`flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5`},oe={class:`flex gap-2 no-print`},se={class:`flex justify-center`},ce={class:`ticket-container`},le={class:`header text-center`},ue=[`src`],de={class:`store-name`},fe={key:1,class:`store-info`},pe={class:`store-info`},me={class:`text-center`},he={class:`ticket-title`},ge={class:`bold-text text-md`},_e={class:`info-table`},ve={class:`bold-text`},ye={key:0},be={class:`items-table`},xe={colspan:`3`},Se={class:`item-name`},Ce={class:`item-calc`},we={class:`item-totals-row`},Te={class:`text-right`},Ee={class:`text-right bold-text`},De={key:0},Oe={class:`totals-table`},ke={key:0},Ae={class:`text-right`},je={class:`total-final`},Me={class:`text-right`},Ne={colspan:`2`,class:`amount-in-words`},Pe={class:`payment-methods`},Fe={class:`info-table`},Ie={class:`text-right`},Le={key:0,class:`border-top-dashed`},Re={class:`text-right bold-text`},ze={key:1},Be={class:`text-right bold-text`},g=h({__name:`Comprobante`,props:{venta:{type:Object,required:!0},comprobantes:{type:Array,default:()=>[]},tienda:{type:Object,default:null}},setup(h){let g=h,_=f(()=>g.tienda??{}),v=f(()=>{let e=_.value.logo_url;return e?/^(https?:|data:|\/storage\/)/i.test(e)?e:`/storage/${e.replace(/^\/+/,``)}`:null}),y=f(()=>[_.value.departamento,_.value.provincia,_.value.distrito].filter(Boolean).join(` - `)),b=o(null),x=f(()=>b.value??g.venta??{}),S=f(()=>x.value.empresa_emisora??null),C=f(()=>!x.value.empresa_emisora_id),w=f(()=>!C.value&&S.value?.ruc?S.value.ruc:_.value.ruc||``),T=f(()=>!C.value&&S.value?.razon_social?S.value.razon_social:_.value.razon_social||_.value.nombre_comercial||_.value.nombre||`MI TIENDA`),Ve=f(()=>!C.value&&S.value?.nombre_comercial?S.value.nombre_comercial:_.value.nombre_comercial||_.value.nombre||_.value.razon_social||`MI TIENDA`),E=f(()=>!C.value&&S.value?.direccion?S.value.direccion:_.value.direccion||``),D=f(()=>!C.value&&S.value?.telefono?S.value.telefono:_.value.telefono||``);f(()=>!C.value&&S.value?.email?S.value.email:_.value.email||``);let O=f(()=>x.value.serie||``),k=f(()=>x.value.correlativo||``),A=f(()=>!O.value&&!k.value?`-`:`${O.value} - ${k.value}`),j=f(()=>x.value.cliente??null),M=f(()=>Array.isArray(x.value.detalles)?x.value.detalles:[]),N=f(()=>x.value.usuario??null),P=e=>new Intl.NumberFormat(`es-PE`,{style:`currency`,currency:`PEN`,minimumFractionDigits:2}).format(Number(e)||0),He=f(()=>{if(!x.value.fecha_emision)return`-`;let e=new Date(x.value.fecha_emision);return isNaN(e.getTime())?x.value.fecha_emision:new Intl.DateTimeFormat(`es-PE`,{day:`2-digit`,month:`2-digit`,year:`numeric`,hour:`2-digit`,minute:`2-digit`}).format(e)}),F=f(()=>Ve.value||T.value||`MI TIENDA`),I=f(()=>j.value&&(j.value.nombre||j.value.razon_social||j.value.razon_social_nombre||j.value.nombres)||`CLIENTE VARIOS`),L=f(()=>j.value&&(j.value.tipo_documento||j.value.tipoDocumento)||`DNI`),R=f(()=>j.value?j.value.numero_documento||j.value.documento||`-`:`00000000`),z=f(()=>j.value?.direccion||null),B=f(()=>N.value?N.value.username:`-`),Ue=e=>e.producto?.nombre||e.descripcion||`Producto`,V=e=>Number(e.cantidad)||0,H=e=>Number(e.precio_unitario??e.precioUnitario??e.precio_venta??0),U=e=>e.subtotal!==void 0&&e.subtotal!==null?Number(e.subtotal)||0:V(e)*H(e),We=f(()=>x.value.subtotal!==void 0&&x.value.subtotal!==null?Number(x.value.subtotal)||0:M.value.reduce((e,t)=>e+U(t),0)),W=f(()=>Number(x.value.descuento_total??x.value.descuento??0)),G=f(()=>x.value.importe_total!==void 0&&x.value.importe_total!==null?Number(x.value.importe_total)||0:x.value.total!==void 0&&x.value.total!==null?Number(x.value.total)||0:We.value-W.value);f(()=>Number(x.value.op_gravada)||0);let K=f(()=>Number(x.value.monto_recibido??x.value.montoRecibido??0)),q=f(()=>Number(x.value.vuelto??0)),J=e=>{let t=[`cero`,`uno`,`dos`,`tres`,`cuatro`,`cinco`,`seis`,`siete`,`ocho`,`nueve`],n=[`diez`,`once`,`doce`,`trece`,`catorce`,`quince`,`dieciseis`,`diecisiete`,`dieciocho`,`diecinueve`,`veinte`],r=[``,``,`veinte`,`treinta`,`cuarenta`,`cincuenta`,`sesenta`,`setenta`,`ochenta`,`noventa`],i=[``,`ciento`,`doscientos`,`trescientos`,`cuatrocientos`,`quinientos`,`seiscientos`,`setecientos`,`ochocientos`,`novecientos`],a=e=>{if(e<10)return t[e];if(e<=20)return n[e-10];if(e<100){let n=r[Math.floor(e/10)];return e%10?`${n} y ${t[e%10]}`:n}if(e===100)return`cien`;let o=i[Math.floor(e/100)],s=e%100;return s?`${o} ${a(s)}`:o};if(e<1e3)return a(e);if(e<1e6){let t=Math.floor(e/1e3),n=e%1e3,r=t===1?`mil`:`${J(t)} mil`;return n?`${r} ${J(n)}`:r}let o=Math.floor(e/1e6),s=e%1e6,c=o===1?`un millon`:`${J(o)} millones`;return s?`${c} ${J(s)}`:c},Ge=f(()=>{let e=Math.max(0,Number(G.value)||0),t=Math.floor(e),n=Math.round((e-t)*100);return n===100&&(t+=1,n=0),`IMPORTE EN LETRAS: ${J(t).toUpperCase()} CON ${String(n).padStart(2,`0`)}/100 SOLES`}),Ke=f(()=>x.value.tipo_pago?.nombre||x.value.tipoPago?.nombre||x.value.forma_pago||`Contado`),qe=()=>{let e=v.value?`<img src="${v.value}" class="logo" alt="Logo de la tienda" />`:``,t=M.value.length?M.value.map(e=>{let t=Ue(e),n=V(e).toFixed(2),r=P(H(e));return`
                    <tr>
                        <td colspan="3">
                            <span class="item-name">
                                ${t}
                            </span>

                            <span class="item-calc">
                                ${n} x ${r}
                            </span>
                        </td>
                    </tr>

                    <tr class="item-totals-row">
                        <td></td>

                        <td class="text-right">
                            ${r}
                        </td>

                        <td class="text-right bold-text">
                            ${P(U(e))}
                        </td>
                    </tr>
                `}).join(``):`<tr>
                <td colspan="3" class="text-center">
                    No hay productos registrados.
                </td>
           </tr>`;return`
        <html>
            <head>
                <title>Comprobante</title>

                <style>
                    html, body {
                        margin: 0;
                        padding: 0;
                        background: #fff;
                        font-family: 'Courier New', Courier, monospace;
                    }

                    body {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                    }

                    .ticket-container {
                        width: 72mm;
                        max-width: 72mm;
                        min-width: 72mm;
                        background: #ffffff;
                        color: #000;
                        padding: 4mm;
                        box-sizing: border-box;
                        font-size: 11px;
                        line-height: 1.2;
                    }

                    .text-center {
                        text-align: center;
                    }

                    .text-right {
                        text-align: right;
                    }

                    .text-left {
                        text-align: left;
                    }

                    .bold-text {
                        font-weight: bold;
                    }

                    .text-md {
                        font-size: 13px;
                    }

                    .divider {
                        border-top: 1px dashed #000;
                        margin: 5px 0;
                    }

                    .double-divider {
                        border-top: 2px double #000;
                        margin: 5px 0;
                    }

                    .logo {
                        display: block;
                        max-width: 30mm;
                        max-height: 16mm;
                        width: auto;
                        height: auto;
                        margin: 0 auto 2px;
                    }

                    .store-name {
                        font-size: 18px;
                        font-weight: bold;
                        text-transform: uppercase;
                        word-break: break-word;
                    }

                    .store-info {
                        font-size: 10px;
                        word-break: break-word;
                    }

                    .ticket-title {
                        font-size: 12px;
                        font-weight: bold;
                        line-height: 1.2;
                    }

                    .info-table,
                    .totals-table,
                    .items-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 11px;
                        table-layout: fixed;
                    }

                    .info-table td {
                        padding: 1px 0;
                        vertical-align: top;
                        word-break: break-word;
                    }

                    .info-table td:first-child {
                        width: 35%;
                    }

                    .info-table td:last-child {
                        width: 65%;
                    }

                    .items-table th {
                        border-bottom: 1px solid #000;
                        padding-bottom: 2px;
                        font-weight: bold;
                    }

                    .items-table th:first-child {
                        width: 50%;
                    }

                    .items-table th:nth-child(2) {
                        width: 23%;
                    }

                    .items-table th:nth-child(3) {
                        width: 27%;
                    }

                    .item-name {
                        display: block;
                        font-weight: bold;
                        word-break: break-word;
                    }

                    .item-calc {
                        display: block;
                        font-size: 10px;
                        color: #333;
                        word-break: break-word;
                    }

                    .item-totals-row td {
                        padding-bottom: 4px;
                    }

                    .totals-table td {
                        padding: 1px 0;
                    }

                    .total-final {
                        font-size: 14px;
                        font-weight: bold;
                    }

                    .amount-in-words {
                        padding-top: 3px !important;
                        font-size: 9px;
                        line-height: 1.25;
                        text-align: left;
                        overflow-wrap: anywhere;
                    }

                    .border-top-dashed {
                        border-top: 1px dashed #000;
                    }


                    @page {
                        size: 80mm auto;
                        margin: 0;
                    }

                    @media print {
                        body {
                            margin: 0;
                        }
                    }
                </style>
            </head>

            <body>
                <div class="ticket-container">

                    <div class="header text-center">

                        ${e}

                        <div class="store-name">
                            ${F.value}
                        </div>

                        ${!C.value&&T.value&&T.value!==F.value?`<div class="store-info">
                                    ${T.value}
                                   </div>`:``}

                        <div class="store-info">

                            ${w.value?`RUC: ${w.value}<br />`:``}

                            ${E.value?`${E.value}<br />`:``}

                            ${y.value?`${y.value}<br />`:``}

                            ${_.value.ubigeo?`Ubigeo: ${_.value.ubigeo}<br />`:``}

                            ${D.value?`Teléfono: ${D.value}<br />`:``}

                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="text-center">

                        <div class="ticket-title">
                            ${x.value.tipo_comprobante?.nombre||x.value.tipoComprobante?.nombre||`BOLETA DE VENTA ELECTRÓNICA`}
                        </div>

                        <div class="bold-text text-md">
                            ${A.value}
                        </div>

                    </div>

                    <div class="divider"></div>

                    <table class="info-table">
                        <tbody>

                            <tr>
                                <td class="bold-text">
                                    Fecha/Hora:
                                </td>

                                <td>
                                    ${He.value}
                                </td>
                            </tr>

                            <tr>
                                <td class="bold-text">
                                    Cajero/a:
                                </td>

                                <td>
                                    ${B.value}
                                </td>
                            </tr>

                            <tr>
                                <td class="bold-text">
                                    Cliente:
                                </td>

                                <td>
                                    ${I.value}
                                </td>
                            </tr>

                            <tr>
                                <td class="bold-text">
                                    ${L.value}:
                                </td>

                                <td>
                                    ${R.value}
                                </td>
                            </tr>

                            ${z.value?`
                                        <tr>
                                            <td class="bold-text">
                                                Dirección:
                                            </td>

                                            <td>
                                                ${z.value}
                                            </td>
                                        </tr>
                                    `:``}

                        </tbody>
                    </table>

                    <div class="double-divider"></div>

                    <table class="items-table">

                        <thead>
                            <tr>
                                <th class="text-left">
                                    Cant. Descrip.
                                </th>

                                <th class="text-right">
                                    P.Unit
                                </th>

                                <th class="text-right">
                                    Total
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            ${t}
                        </tbody>

                    </table>

                    <div class="divider"></div>

                   
                <table class="totals-table">
                  <tbody>
                     ${W.value>0?`
                    <tr>
                        <td class="text-left">Descuento:</td>
                        <td class="text-right">- ${P(W.value)}</td>
                    </tr>
                            `:``}

                <tr class="total-final">
                <td class="text-left">TOTAL A PAGAR:</td>
                <td class="text-right">${P(G.value)}</td>
                </tr>

            <tr>
                <td colspan="2" class="amount-in-words">${Ge.value}</td>
             </tr>
        </tbody>
        </table>

                    <div class="divider"></div>

                    <div class="payment-methods">

                        <div class="bold-text text-left">
                            Forma de Pago:
                        </div>

                        <table class="info-table">

                            <tbody>

                                <tr>
                                    <td>
                                        - ${Ke.value}:
                                    </td>

                                    <td class="text-right">
                                        ${P(G.value)}
                                    </td>
                                </tr>

                                ${K.value>0?`
                                            <tr class="border-top-dashed">
                                                <td class="bold-text">
                                                    - Entregado:
                                                </td>

                                                <td class="text-right bold-text">
                                                    ${P(K.value)}
                                                </td>
                                            </tr>
                                        `:``}

                                ${q.value>0?`
                                            <tr>
                                                <td class="bold-text">
                                                    - Vuelto:
                                                </td>

                                                <td class="text-right bold-text">
                                                    ${P(q.value)}
                                                </td>
                                            </tr>
                                        `:``}

                            </tbody>

                        </table>

                    </div>

                    <div class="double-divider"></div>

                    <div class="footer text-center">

                        <div>
                            Representación impresa de la Boleta de Venta
                            Electrónica.
                        </div>

                        <div class="bold-text mt-1">
                            ¡Gracias por su compra!
                        </div>

                    </div>

                </div>
            </body>
        </html>
    `},Y=null,X=[],Z=0,Q=!1,Je=()=>{let e=X[Z];if(!e||!Y){Q=!1,Y?.close(),$();return}b.value=e,Y.document.open(),Y.document.write(qe()),Y.document.close(),Y.focus(),setTimeout(()=>{Y?.print()},500)},Ye=()=>{Z+=1,Je()},Xe=()=>{if(Q)return;X=g.comprobantes.length>0?g.comprobantes:[g.venta],Z=0,Q=!0;let e=Math.max(0,(window.screen.width-800)/2),t=Math.max(0,(window.screen.height-900)/2);if(Y=window.open(``,`Comprobante`,`
            width=800,
            height=900,
            left=${e},
            top=${t},
            resizable=yes,
            scrollbars=yes
        `),!Y){Q=!1,alert(`El navegador bloqueó la ventana de impresión. Permite ventanas emergentes para esta aplicación.`);return}Y.onafterprint=Ye,Je()},$=()=>{ne.visit(route(`ventas.create`),{replace:!0,preserveScroll:!1,preserveState:!1})};return r(()=>{Y?.close()}),(r,o)=>(n(),ee(te,null,{default:i(()=>[s(`div`,ie,[a(re,{class:`shadow-sm`},{content:i(()=>[s(`div`,ae,[s(`div`,oe,[a(l(m),{label:`Volver a crear venta`,icon:`pi pi-plus`,severity:`secondary`,outlined:``,onClick:$}),a(l(m),{label:`Imprimir`,icon:`pi pi-print`,onClick:Xe})])]),s(`div`,se,[s(`div`,ce,[s(`div`,le,[v.value?(n(),c(`img`,{key:0,src:v.value,class:`logo`,alt:`Logo de la tienda`},null,8,ue)):p(``,!0),s(`div`,de,t(F.value),1),!C.value&&T.value&&T.value!==F.value?(n(),c(`div`,fe,t(T.value),1)):p(``,!0),s(`div`,pe,[w.value?(n(),c(d,{key:0},[u(` RUC: `+t(w.value),1),o[0]||=s(`br`,null,null,-1)],64)):p(``,!0),E.value?(n(),c(d,{key:1},[u(t(E.value),1),o[1]||=s(`br`,null,null,-1)],64)):p(``,!0),y.value?(n(),c(d,{key:2},[u(t(y.value),1),o[2]||=s(`br`,null,null,-1)],64)):p(``,!0),_.value.ubigeo?(n(),c(d,{key:3},[u(` Ubigeo: `+t(_.value.ubigeo),1),o[3]||=s(`br`,null,null,-1)],64)):p(``,!0),D.value?(n(),c(d,{key:4},[u(` Teléfono: `+t(D.value),1),o[4]||=s(`br`,null,null,-1)],64)):p(``,!0)])]),o[18]||=s(`div`,{class:`divider`},null,-1),s(`div`,me,[s(`div`,he,t(x.value.tipo_comprobante?.nombre||x.value.tipoComprobante?.nombre||`BOLETA DE VENTA ELECTRÓNICA`),1),s(`div`,ge,t(A.value),1)]),o[19]||=s(`div`,{class:`divider`},null,-1),s(`table`,_e,[s(`tbody`,null,[s(`tr`,null,[o[5]||=s(`td`,{class:`bold-text`},`Fecha/Hora:`,-1),s(`td`,null,t(He.value),1)]),s(`tr`,null,[o[6]||=s(`td`,{class:`bold-text`},`Cajero/a:`,-1),s(`td`,null,t(B.value),1)]),s(`tr`,null,[o[7]||=s(`td`,{class:`bold-text`},`Cliente:`,-1),s(`td`,null,t(I.value),1)]),s(`tr`,null,[s(`td`,ve,t(L.value)+`: `,1),s(`td`,null,t(R.value),1)]),z.value?(n(),c(`tr`,ye,[o[8]||=s(`td`,{class:`bold-text`},`Dirección:`,-1),s(`td`,null,t(z.value),1)])):p(``,!0)])]),o[20]||=s(`div`,{class:`double-divider`},null,-1),s(`table`,be,[o[11]||=s(`thead`,null,[s(`tr`,null,[s(`th`,{class:`text-left`},` Cant. Descrip. `),s(`th`,{class:`text-right`},`P.Unit`),s(`th`,{class:`text-right`},`Total`)])],-1),s(`tbody`,null,[(n(!0),c(d,null,e(M.value,(e,r)=>(n(),c(d,{key:e.id??r},[s(`tr`,null,[s(`td`,xe,[s(`span`,Se,t(Ue(e)),1),s(`span`,Ce,t(V(e).toFixed(2))+` x `+t(P(H(e))),1)])]),s(`tr`,we,[o[9]||=s(`td`,null,null,-1),s(`td`,Te,t(P(H(e))),1),s(`td`,Ee,t(P(U(e))),1)])],64))),128)),M.value.length===0?(n(),c(`tr`,De,[...o[10]||=[s(`td`,{colspan:`3`,class:`text-center`},` No hay productos registrados. `,-1)]])):p(``,!0)])]),o[21]||=s(`div`,{class:`divider`},null,-1),s(`table`,Oe,[s(`tbody`,null,[W.value>0?(n(),c(`tr`,ke,[o[12]||=s(`td`,{class:`text-left`},`Descuento:`,-1),s(`td`,Ae,` - `+t(P(W.value)),1)])):p(``,!0),o[14]||=s(`tr`,{class:``},null,-1),s(`tr`,je,[o[13]||=s(`td`,{class:`text-left`},` TOTAL A PAGAR: `,-1),s(`td`,Me,t(P(G.value)),1)]),s(`tr`,null,[s(`td`,Ne,t(Ge.value),1)])])]),o[22]||=s(`div`,{class:`divider`},null,-1),o[23]||=s(`div`,null,`IGV aplicado: 18%`,-1),s(`div`,Pe,[o[17]||=s(`div`,{class:`bold-text text-left`},` Forma de Pago: `,-1),s(`table`,Fe,[s(`tbody`,null,[s(`tr`,null,[s(`td`,null,` - `+t(Ke.value)+`: `,1),s(`td`,Ie,t(P(G.value)),1)]),K.value>0?(n(),c(`tr`,Le,[o[15]||=s(`td`,{class:`bold-text`},` - Entregado: `,-1),s(`td`,Re,t(P(K.value)),1)])):p(``,!0),q.value>0?(n(),c(`tr`,ze,[o[16]||=s(`td`,{class:`bold-text`},`- Vuelto:`,-1),s(`td`,Be,t(P(q.value)),1)])):p(``,!0)])])]),o[24]||=s(`div`,{class:`double-divider`},null,-1),o[25]||=s(`div`,{class:`footer text-center`},[s(`div`,null,` Representación impresa de la Boleta de Venta Electrónica. `),s(`div`,{class:`bold-text mt-1`},` ¡Gracias por su compra! `)],-1)])])]),_:1})])]),_:1}))}},[[`__scopeId`,`data-v-76a59425`]]);export{g as default};