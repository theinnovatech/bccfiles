import{aW as b,V as c,P as m,o as h,c as g,U as y,S as x,aX as S}from"./app-n6ggwVtm.js";import{s as _}from"./index-Cr6kfdXf.js";function a(t){"@babel/helpers - typeof";return a=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},a(t)}function P(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}function k(t,e){for(var n=0;n<e.length;n++){var r=e[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(t,$(r.key),r)}}function w(t,e,n){return e&&k(t.prototype,e),Object.defineProperty(t,"prototype",{writable:!1}),t}function $(t){var e=j(t,"string");return a(e)=="symbol"?e:e+""}function j(t,e){if(a(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var r=n.call(t,e);if(a(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(t)}var G=(function(){function t(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:function(){};P(this,t),this.element=e,this.listener=n}return w(t,[{key:"bindScrollListener",value:function(){this.scrollableParents=b(this.element);for(var n=0;n<this.scrollableParents.length;n++)this.scrollableParents[n].addEventListener("scroll",this.listener)}},{key:"unbindScrollListener",value:function(){if(this.scrollableParents)for(var n=0;n<this.scrollableParents.length;n++)this.scrollableParents[n].removeEventListener("scroll",this.listener)}},{key:"destroy",value:function(){this.unbindScrollListener(),this.element=null,this.listener=null,this.scrollableParents=null}}])})();function u(t){"@babel/helpers - typeof";return u=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},u(t)}function A(t){return T(t)||O(t)||I(t)||C()}function C(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function I(t,e){if(t){if(typeof t=="string")return p(t,e);var n={}.toString.call(t).slice(8,-1);return n==="Object"&&t.constructor&&(n=t.constructor.name),n==="Map"||n==="Set"?Array.from(t):n==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?p(t,e):void 0}}function O(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function T(t){if(Array.isArray(t))return p(t)}function p(t,e){(e==null||e>t.length)&&(e=t.length);for(var n=0,r=Array(e);n<e;n++)r[n]=t[n];return r}function E(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}function z(t,e){for(var n=0;n<e.length;n++){var r=e[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(t,v(r.key),r)}}function L(t,e,n){return e&&z(t.prototype,e),Object.defineProperty(t,"prototype",{writable:!1}),t}function f(t,e,n){return(e=v(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function v(t){var e=B(t,"string");return u(e)=="symbol"?e:e+""}function B(t,e){if(u(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var r=n.call(t,e);if(u(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(t)}var J=(function(){function t(e){var n=e.init,r=e.type;E(this,t),f(this,"helpers",void 0),f(this,"type",void 0),this.helpers=new Set(n),this.type=r}return L(t,[{key:"add",value:function(n){this.helpers.add(n)}},{key:"update",value:function(){}},{key:"delete",value:function(n){this.helpers.delete(n)}},{key:"clear",value:function(){this.helpers.clear()}},{key:"get",value:function(n,r){var o=this._get(n,r),i=o?this._recursive(A(this.helpers),o):null;return c(i)?i:null}},{key:"_isMatched",value:function(n,r){var o,i=n?.parent;return(i==null||(o=i.vnode)===null||o===void 0?void 0:o.key)===r||i&&this._isMatched(i,r)||!1}},{key:"_get",value:function(n,r){var o,i;return((o=r||n?.$slots)===null||o===void 0||(i=o.default)===null||i===void 0?void 0:i.call(o))||null}},{key:"_recursive",value:function(){var n=this,r=arguments.length>0&&arguments[0]!==void 0?arguments[0]:[],o=arguments.length>1&&arguments[1]!==void 0?arguments[1]:[],i=[];return o.forEach(function(l){l.children instanceof Array?i=i.concat(n._recursive(r,l.children)):l.type.name===n.type?i.push(l):c(l.key)&&(i=i.concat(r.filter(function(d){return n._isMatched(d,l.key)}).map(function(d){return d.vnode})))}),i}}])})();function Q(t,e){if(t){var n=t.props;if(n){var r=e.replace(/([a-z])([A-Z])/g,"$1-$2").toLowerCase(),o=Object.prototype.hasOwnProperty.call(n,r)?r:e;return t.type.extends.props[e].type===Boolean&&n[o]===""?!0:n[o]}}return null}var M=`
    .p-inputtext {
        font-family: inherit;
        font-feature-settings: inherit;
        font-size: 1rem;
        color: dt('inputtext.color');
        background: dt('inputtext.background');
        padding-block: dt('inputtext.padding.y');
        padding-inline: dt('inputtext.padding.x');
        border: 1px solid dt('inputtext.border.color');
        transition:
            background dt('inputtext.transition.duration'),
            color dt('inputtext.transition.duration'),
            border-color dt('inputtext.transition.duration'),
            outline-color dt('inputtext.transition.duration'),
            box-shadow dt('inputtext.transition.duration');
        appearance: none;
        border-radius: dt('inputtext.border.radius');
        outline-color: transparent;
        box-shadow: dt('inputtext.shadow');
    }

    .p-inputtext:enabled:hover {
        border-color: dt('inputtext.hover.border.color');
    }

    .p-inputtext:enabled:focus {
        border-color: dt('inputtext.focus.border.color');
        box-shadow: dt('inputtext.focus.ring.shadow');
        outline: dt('inputtext.focus.ring.width') dt('inputtext.focus.ring.style') dt('inputtext.focus.ring.color');
        outline-offset: dt('inputtext.focus.ring.offset');
    }

    .p-inputtext.p-invalid {
        border-color: dt('inputtext.invalid.border.color');
    }

    .p-inputtext.p-variant-filled {
        background: dt('inputtext.filled.background');
    }

    .p-inputtext.p-variant-filled:enabled:hover {
        background: dt('inputtext.filled.hover.background');
    }

    .p-inputtext.p-variant-filled:enabled:focus {
        background: dt('inputtext.filled.focus.background');
    }

    .p-inputtext:disabled {
        opacity: 1;
        background: dt('inputtext.disabled.background');
        color: dt('inputtext.disabled.color');
    }

    .p-inputtext::placeholder {
        color: dt('inputtext.placeholder.color');
    }

    .p-inputtext.p-invalid::placeholder {
        color: dt('inputtext.invalid.placeholder.color');
    }

    .p-inputtext-sm {
        font-size: dt('inputtext.sm.font.size');
        padding-block: dt('inputtext.sm.padding.y');
        padding-inline: dt('inputtext.sm.padding.x');
    }

    .p-inputtext-lg {
        font-size: dt('inputtext.lg.font.size');
        padding-block: dt('inputtext.lg.padding.y');
        padding-inline: dt('inputtext.lg.padding.x');
    }

    .p-inputtext-fluid {
        width: 100%;
    }
`,H={root:function(e){var n=e.instance,r=e.props;return["p-inputtext p-component",{"p-filled":n.$filled,"p-inputtext-sm p-inputfield-sm":r.size==="small","p-inputtext-lg p-inputfield-lg":r.size==="large","p-invalid":n.$invalid,"p-variant-filled":n.$variant==="filled","p-inputtext-fluid":n.$fluid}]}},K=m.extend({name:"inputtext",style:M,classes:H}),V={name:"BaseInputText",extends:_,style:K,provide:function(){return{$pcInputText:this,$parentInstance:this}}};function s(t){"@babel/helpers - typeof";return s=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},s(t)}function U(t,e,n){return(e=W(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function W(t){var e=F(t,"string");return s(e)=="symbol"?e:e+""}function F(t,e){if(s(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var r=n.call(t,e);if(s(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var N={name:"InputText",extends:V,inheritAttrs:!1,methods:{onInput:function(e){this.writeValue(e.target.value,e)}},computed:{attrs:function(){return y(this.ptmi("root",{context:{filled:this.$filled,disabled:this.disabled}}),this.formField)},dataP:function(){return x(U({invalid:this.$invalid,fluid:this.$fluid,filled:this.$variant==="filled"},this.size,this.size))}}},X=["value","name","disabled","aria-invalid","data-p"];function Z(t,e,n,r,o,i){return h(),g("input",y({type:"text",class:t.cx("root"),value:t.d_value,name:t.name,disabled:t.disabled,"aria-invalid":t.$invalid||void 0,"data-p":i.dataP,onInput:e[0]||(e[0]=function(){return i.onInput&&i.onInput.apply(i,arguments)})},i.attrs),null,16,X)}N.render=Z;var R=S();export{G as C,R as O,J as _,Q as g,N as s};
