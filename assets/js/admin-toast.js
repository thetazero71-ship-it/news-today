/**
 * Admin notifications: toasts + a styled confirm dialog.
 *
 * Replaces the browser's native alert()/confirm() which block the whole screen
 * and break the flow of the admin panel.
 *
 *   adminToast('تم الحذف', 'success');
 *   adminConfirm('هل أنت متأكد؟').then(ok => { if (ok) form.submit(); });
 */
(function () {
  'use strict';
  if (window.adminToast) return;

  const ICONS = {
    success: '<svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14Zm3.97-8.97-5.6 5.6a.75.75 0 0 1-1.06 0L3.75 9.09l.94-.94 2.06 2.06 5.06-5.06.96.88Z"/></svg>',
    error: '<svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14ZM5.28 5.28a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94Z"/></svg>',
    warning: '<svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8.98 1.57a1.13 1.13 0 0 0-1.96 0L.42 13.29A1.13 1.13 0 0 0 1.41 15h13.18a1.13 1.13 0 0 0 .99-1.71L8.98 1.57ZM8 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 5Zm0 8.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/></svg>',
    info: '<svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14Zm0-3a.75.75 0 0 1-.75-.75V8a.75.75 0 0 1 .75-.75h.25a.75.75 0 0 1 .75.75v3.25A.75.75 0 0 1 8 75Zm0-3.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/></svg>'
  };

  function css() {
    if (document.getElementById('admin-toast-css')) return;
    const style = document.createElement('style');
    style.id = 'admin-toast-css';
    style.textContent = `
      .admin-toast-wrap{position:fixed;top:78px;left:0;right:0;z-index:10900;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none}
      .admin-toast{display:flex;align-items:flex-start;gap:10px;max-width:min(560px,92vw);padding:12px 16px;border-radius:12px;
        box-shadow:0 10px 30px rgba(15,23,42,.18);font-weight:600;font-size:.94rem;line-height:1.55;
        pointer-events:auto;position:relative;overflow:hidden;animation:adminToastIn .22s ease-out;color:#fff}
      .admin-toast.out{animation:adminToastOut .2s ease-in forwards}
      .admin-toast.success{background:#059669}.admin-toast.error{background:#dc2626}
      .admin-toast.warning{background:#d97706}.admin-toast.info{background:#2563eb}
      .admin-toast svg{flex:0 0 auto;margin-top:2px}
      .admin-toast .admin-toast-text{flex:1;word-break:break-word}
      .admin-toast .admin-toast-close{background:transparent;border:0;color:rgba(255,255,255,.85);cursor:pointer;font-size:1.1rem;line-height:1;padding:0 2px}
      .admin-toast .admin-toast-bar{position:absolute;bottom:0;left:0;height:3px;background:rgba(255,255,255,.55);width:100%;transform-origin:right}
      @keyframes adminToastIn{from{opacity:0;transform:translateY(-12px) scale(.97)}to{opacity:1;transform:none}}
      @keyframes adminToastOut{to{opacity:0;transform:translateY(-8px) scale(.97)}}
      .admin-confirm-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;z-index:11000;padding:20px;animation:adminFade .15s ease-out}
      @keyframes adminFade{from{opacity:0}to{opacity:1}}
      .admin-confirm{background:#fff;border-radius:16px;max-width:460px;width:100%;box-shadow:0 24px 60px rgba(2,6,23,.35);overflow:hidden;animation:adminPop .18s ease-out}
      @keyframes adminPop{from{transform:translateY(10px) scale(.98);opacity:0}to{transform:none;opacity:1}}
      .admin-confirm-body{padding:22px 22px 8px;display:flex;gap:14px;align-items:flex-start}
      .admin-confirm-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;background:#fef3c7;color:#d97706}
      .admin-confirm.danger .admin-confirm-icon{background:#fee2e2;color:#dc2626}
      .admin-confirm-icon svg{width:22px;height:22px;fill:currentColor}
      .admin-confirm-title{font-weight:700;font-size:1.02rem;color:#0f172a;margin-bottom:2px}
      .admin-confirm-text{color:#475569;font-size:.93rem;white-space:pre-line;line-height:1.6}
      .admin-confirm-actions{display:flex;gap:10px;justify-content:flex-end;padding:16px 22px 20px}
      .admin-confirm-actions button{border:0;border-radius:10px;padding:9px 18px;font-weight:700;font-size:.92rem;cursor:pointer;transition:filter .15s}
      .admin-confirm-actions .btn-cancel{background:#e2e8f0;color:#334155}
      .admin-confirm-actions .btn-ok{background:#0f766e;color:#fff}
      .admin-confirm.danger .admin-confirm-actions .btn-ok{background:#dc2626}
      .admin-confirm-actions button:hover{filter:brightness(.95)}
      html[dir="rtl"] .admin-confirm-actions{justify-content:flex-start}
    `;
    document.head.appendChild(style);
  }

  function wrap() {
    let el = document.getElementById('adminToastWrap');
    if (!el) {
      el = document.createElement('div');
      el.id = 'adminToastWrap';
      el.className = 'admin-toast-wrap';
      document.body.appendChild(el);
    }
    return el;
  }

  function adminToast(message, type = 'info', ms = 3800) {
    css();
    if (!message) return;
    const kind = ICONS[type] ? type : 'info';
    const box = document.createElement('div');
    box.className = 'admin-toast ' + kind;
    box.setAttribute('role', 'status');

    const icon = document.createElement('span');
    icon.innerHTML = ICONS[kind];

    const text = document.createElement('span');
    text.className = 'admin-toast-text';
    text.textContent = String(message);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'admin-toast-close';
    close.setAttribute('aria-label', 'إغلاق');
    close.textContent = '×';

    const bar = document.createElement('span');
    bar.className = 'admin-toast-bar';

    box.appendChild(icon);
    box.appendChild(text);
    box.appendChild(close);
    box.appendChild(bar);
    wrap().appendChild(box);

    let left = ms;
    let timer = setInterval(() => {
      left -= 60;
      bar.style.transform = 'scaleX(' + Math.max(0, left / ms) + ')';
      if (left <= 0) {
        clearInterval(timer);
        dismiss();
      }
    }, 60);

    function dismiss() {
      clearInterval(timer);
      if (!box.isConnected) return;
      box.classList.add('out');
      setTimeout(() => box.remove(), 220);
    }

    close.addEventListener('click', dismiss);
    // hovering pauses the countdown, leaving resumes it with what is left
    box.addEventListener('mouseenter', () => clearInterval(timer));
    box.addEventListener('mouseleave', () => {
      if (!box.isConnected || box.classList.contains('out') || left <= 0) return;
      timer = setInterval(() => {
        left -= 60;
        bar.style.transform = 'scaleX(' + Math.max(0, left / ms) + ')';
        if (left <= 0) {
          clearInterval(timer);
          dismiss();
        }
      }, 60);
    });
    return box;
  }

  const ICON_QUESTION =
    '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1Zm.9 10.5H7.1v-1.2h1.8v1.2Zm1.7-4.9c-.6.7-1.1 1-1.1 1.8v.4H7.6v-.5c0-1 .5-1.6 1.2-2.3.6-.6.9-1 .9-1.6 0-.7-.5-1.2-1.2-1.2s-1.2.5-1.2 1.3H5.5C5.5 3.6 6.5 2.7 8.1 2.7c1.6 0 2.7.9 2.7 2.3 0 .8-.4 1.4-1.2 2.1Z"/></svg>';

  function adminConfirm(message, options) {
    css();
    const opts = options || {};
    const title = opts.title || 'تأكيد الإجراء';

    return new Promise((resolve) => {
      const backdrop = document.createElement('div');
      backdrop.className = 'admin-confirm-backdrop';

      const box = document.createElement('div');
      box.className = 'admin-confirm' + (opts.danger ? ' danger' : '');
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');

      const icon = document.createElement('div');
      icon.className = 'admin-confirm-icon';
      icon.innerHTML = ICON_QUESTION;

      const body = document.createElement('div');
      body.className = 'admin-confirm-body';
      const col = document.createElement('div');
      const t = document.createElement('div');
      t.className = 'admin-confirm-title';
      t.textContent = title;
      const x = document.createElement('div');
      x.className = 'admin-confirm-text';
      x.textContent = String(message || '');
      col.appendChild(t);
      col.appendChild(x);
      body.appendChild(icon);
      body.appendChild(col);

      const actions = document.createElement('div');
      actions.className = 'admin-confirm-actions';
      const cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = 'btn-cancel';
      cancel.textContent = opts.cancelText || 'إلغاء';
      const ok = document.createElement('button');
      ok.type = 'button';
      ok.className = 'btn-ok';
      ok.textContent = opts.confirmText || 'تأكيد';
      actions.appendChild(cancel);
      actions.appendChild(ok);

      box.appendChild(body);
      box.appendChild(actions);
      backdrop.appendChild(box);
      document.body.appendChild(backdrop);

      function done(value) {
        document.removeEventListener('keydown', onKey);
        if (backdrop.isConnected) backdrop.remove();
        resolve(value);
      }
      function onKey(e) {
        if (e.key === 'Escape') done(false);
        if (e.key === 'Enter') done(true);
      }
      document.addEventListener('keydown', onKey);
      cancel.addEventListener('click', () => done(false));
      ok.addEventListener('click', () => done(true));
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) done(false);
      });
      setTimeout(() => ok.focus(), 30);
    });
  }

  /**
   * Wires every form that still carries onsubmit="return confirm(...)":
   * the dialog replaces the native popup and the form submits itself.
   */
  function upgradeConfirmForms() {
    document.querySelectorAll('form[onsubmit*="confirm("]').forEach((form) => {
      const raw = form.getAttribute('onsubmit') || '';
      const match = raw.match(/confirm\(([\s\S]*)\)/);
      if (!match) return;
      let message = match[1].trim();
      // strip the quotes the inline attribute added around the message
      message = message.replace(/^['"]/, '').replace(/['"];?$/, '').replace(/\\n/g, '\n').replace(/\\'/g, "'");
      const danger = /حذف|حذفها|سيتم/.test(message);
      form.removeAttribute('onsubmit');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        adminConfirm(message, { danger: danger, title: danger ? 'تأكيد الحذف' : 'تأكيد' }).then((ok) => {
          if (!ok) return;
          form.dataset.confirmed = '1';
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        });
      });
    });
  }

  window.adminToast = adminToast;
  window.adminConfirm = adminConfirm;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', upgradeConfirmForms);
  } else {
    upgradeConfirmForms();
  }
})();