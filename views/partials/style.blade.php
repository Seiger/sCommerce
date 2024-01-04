<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
    #action-btns{text-align:center;width:230px;}
    #actions .btn-group .btn .fas, #_actions .btn-group .btn .fas, .sectionTrans .btn-group .btn .fa, .sectionTrans .btn-group .btn .fas{display:none;width:1em;font-size:1em;text-align:center;}
    #copyright{position:fixed;bottom:0;right:0;}
    #copyright img{width:35px;}
    #preview.form-control{max-width:85px;background:#CECECF;}
    input[type=checkbox], input[type=radio] {padding:0.5em;}
    span[data-actual]::before{content:attr(data-actual);}
    ul.select2-selection__rendered{margin-left:unset;}
    .alertify .ajs-footer .ajs-buttons .ajs-button.ajs-ok {color:#fff;background-color:#d9534f;border-color:#d9534f;}
    .badge.bg-seigerit{background-color:#0057B8 !important;color:#FFD700;font-size:85%;}
    .hidden{display:none;}
    .form-row .col{margin-right:20px;}
    .tab-row-container{background:#CECECF;}
    .tab-row .tab a{color:#0D0D0D;font-family:'Roboto';font-size:14px;font-weight:400;line-height:115%;text-transform:uppercase;padding:16px;}
    .scom-conters{margin-bottom:24px;margin-top:8px;}
    .scom-conters-item{margin-right:24px;}
    .scom-status-title{font-size:14px;font-weight:400;line-height:120%;}
    .scom-all{color:var(--text-text-base, #0D0D0D);font-size:16px;font-weight:700;line-height:120%;}
    .scom-active{color:var(--brand-green, #009891);}
    .scom-disactive{color:var(--brand-pink, #EF4B67);}
    .form-control.scom-input{height:42px;font-size:16px;font-weight:400;line-height:120%;padding:16px 12px;}
    .scom-clear-search{padding:5px;margin-left:-44px;z-index:10;cursor:pointer;}
    .scom-table thead th, .scom-table thead th button{color:var(--text-text-middle, #63666B);font-size:12px;font-weight:700;line-height:120%;padding-top:12px;padding-bottom:12px;}
    .scom-table thead th:first-of-type{padding-left:43px;}
    .scom-table thead th:last-of-type{padding-right:43px;}
    .scom-table tbody tr td{color:#0D0D0D;font-size:14px;font-weight:400;line-height:120%;}
    .scom-table tbody tr{border-color:var(--secondary-gray, #EAEAEA);border-width:1px;}
    .scom-table tbody tr:last-child{border-bottom: 1px solid var(--secondary-gray, #EAEAEA);}
    .scom-table tbody tr td:first-child{padding-left:43px;}
    .scom-table tbody tr td:last-child{padding-right:43px;}
    .scom-table{background: var(--secondary-white, #FFF);}
    .seiger__bottom{display:flex;align-items:center;justify-content:space-between;}
    .seiger__bottom > *{flex: 1 100%;}
    .seiger__bottom > *:first-of-type, .seiger__bottom > *:last-of-type{flex: 1 53%;}
    .seiger__list{display:flex;align-items:center;justify-content:flex-end;}
    .seiger__label{display:inline-block;color:#63666b;font-family:inherit;font-size:14px;font-weight:400;line-height:130%;margin-right:10px;white-space:nowrap;}
    .seiger__module-table{width:calc(100% + 60px);margin-left:-40px;}
    .dropdown{position:relative;}
    .dropdown .dropdown__title{padding:8px 12px;display:flex;align-items:center;border-radius:6px;border:1px solid #cececf;background:#fff;cursor:pointer;outline:none;}
    .dropdown .dropdown__title span{display:inline-block;margin-right:4px;font-family:inherit;font-size:14px;font-weight:400;line-height:120%;}
    .dropdown .dropdown__menu{visibility:hidden;pointer-events:none;position:absolute;bottom:0;transform:translateY(100%);left:0;width:max-content;min-width:100%;list-style:none;margin:0;padding:0;background:#fff;border-radius:4px;border:1px solid #eaeaea;z-index:999;}
    .dropdown.active .dropdown__menu{visibility:visible;pointer-events:all;}
    .dropdown .dropdown__menu-link{padding:8px 12px;white-space:nowrap;cursor:pointer;color:#0d0d0d;text-decoration:none;display:block;}
    .dropdown .dropdown__menu-link:hover{text-decoration:none;background:#F4F4EF;}
    @media (max-width: 840px){
        #action-btns{width:80px;}
        #actions .btn-group .btn .fas, #_actions .btn-group .btn .fas,
        .sectionTrans .btn-group .btn .fa, .sectionTrans .btn-group .btn .fas{display:inline-block;}
        .sectionTrans .btn-group .btn span{display:none;}
    }
    @media screen and (max-width: 768px){
        .seiger__bottom{flex-wrap:wrap;}
    }
</style>