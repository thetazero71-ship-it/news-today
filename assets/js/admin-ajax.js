/**
 * Admin AJAX helpers: run a form in the background and update the page in
 * place. No navigation, no full reload, no jumping back to the top.
 */
(function () {
  'use strict';
  if (window.postForm) return;

  /**
   * Submits a form with fetch() and returns the parsed response.
   * Throws with a readable message when the server refuses.
   */
  window.postForm = async function (form, options) {
    const opts = options || {};
    const body = new FormData(form);
    if (!body.has('respond_with')) body.append('respond_with', 'json');

    const res = await fetch(form.action || window.location.href, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    const text = await res.text();
    if (!text) {
      if (!res.ok) throw new Error('تعذّر الاتصال بالخادم (' + res.status + ')');
      return { ok: true };
    }

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      if (!res.ok) throw new Error('خطأ من الخادم (' + res.status + ')');
      // a normal redirect/HTML answer: the work is done
      return { ok: true };
    }

    if (data.ok === false) {
      const err = new Error(data.message || data.error || 'فشلت العملية');
      err.payload = data;
      throw err;
    }
    return data;
  };

  /** Adds a pending state to a button while its request runs. */
  function busy(btn, on, label) {
    if (!btn) return;
    if (on) {
      btn.dataset.label = btn.innerHTML;
      btn.disabled = true;
      if (label) btn.innerHTML = label;
    } else {
      btn.disabled = false;
      if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
    }
  }

  window.adminBusy = busy;

  /**
   * Favourite / pin buttons: toggle in place, never navigate.
   */
  function bindPinButtons() {
    document.querySelectorAll('form[id^="pinForm_"]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button');
        const icon = form.querySelector('i');
        if (!btn || btn.disabled) return;

        const wasOn = btn.classList.contains('is-pinned');
        btn.classList.toggle('is-pinned', !wasOn);
        if (icon) icon.className = !wasOn ? 'bi bi-pin-angle-fill text-warning' : 'bi bi-pin-angle';

        try {
          const data = await window.postForm(form);
          if (window.adminToast) {
            window.adminToast(
              (data.message || (wasOn ? 'أُزيل من المفضلة' : 'أُضيف إلى المفضلة')),
              wasOn ? 'info' : 'success',
              2200
            );
          }
          document.dispatchEvent(new CustomEvent('admin:favorite-changed', { detail: data }));
        } catch (err) {
          btn.classList.toggle('is-pinned', wasOn);
          if (icon) icon.className = wasOn ? 'bi bi-pin-angle-fill text-warning' : 'bi bi-pin-angle';
          if (window.adminToast) window.adminToast(err.message, 'error');
        }
      });
    });
  }

  /**
   * Auto-publish switch in the sources table.
   */
  function bindAutoButtons() {
    document.querySelectorAll('.js-auto-toggle').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button');
        if (!btn || btn.disabled) return;
        busy(btn, true, '<span class="spinner-border spinner-border-sm"></span>');
        try {
          const data = await window.postForm(form);
          const on = !!(data.auto_publish);
          btn.className = 'btn btn-sm js-auto-btn ' + (on ? 'btn-success' : 'btn-outline-secondary');
          const icon = btn.querySelector('i');
          if (icon) icon.className = 'bi ' + (on ? 'bi-broadcast' : 'bi-broadcast-pin');
          const label = document.createElement('span');
          label.textContent = on ? ' مفعّل' : ' يدوي';
          if (icon) icon.after(label);
          else btn.appendChild(label);
          if (window.adminToast) window.adminToast(data.message || 'تم التحديث', 'success');
        } catch (err) {
          busy(btn, false);
          if (window.adminToast) window.adminToast(err.message, 'error');
        }
      });
    });
  }

  /**
   * Delete buttons: confirm, then remove the row instead of reloading.
   */
  function bindDeleteButtons() {
    document.querySelectorAll('form[data-ajax-delete]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const confirmMsg = form.getAttribute('data-confirm') || 'هل تريد الحذف نهائياً؟';
        const ok = window.adminConfirm
          ? await window.adminConfirm(confirmMsg, { title: 'تأكيد الحذف', danger: true })
          : window.confirm(confirmMsg);
        if (!ok) return;

        const btn = form.querySelector('button');
        busy(btn, true, '<span class="spinner-border spinner-border-sm"></span>');
        try {
          const data = await window.postForm(form);
          const row = form.closest('tr');
          if (row) {
            row.style.transition = 'opacity .2s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 200);
          }
          if (window.adminToast) window.adminToast(data.message || 'تم الحذف', 'success');
        } catch (err) {
          busy(btn, false);
          if (window.adminToast) window.adminToast(err.message, 'error');
        }
      });
    });
  }

  /**
   * Bulk bar: runs the operation in the background and updates the rows.
   */
  function bindBulkForm() {
    const form = document.getElementById('bulkSourcesForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const action = (document.getElementById('bulkAction') || {}).value;
      const catEl = document.getElementById('bulkCategory');
      const checked = Array.from(document.querySelectorAll('.source-check:checked'));
      if (!action) {
        if (window.adminToast) window.adminToast('اختر العملية أولاً', 'warning');
        return;
      }
      if (!checked.length) {
        if (window.adminToast) window.adminToast('لم تحدد أي مصدر', 'warning');
        return;
      }

      let message = 'تأكيد: ' + action + ' — ' + checked.length + ' مصدر؟';
      if (action === 'delete') message = 'سيتم حذف ' + checked.length + ' مصدراً نهائياً.';
      const ok = window.adminConfirm
        ? await window.adminConfirm(message, { title: 'تأكيد العملية', danger: action === 'delete' })
        : window.confirm(message);
      if (!ok) return;

      const btn = document.getElementById('bulkSubmit');
      busy(btn, true, '<span class="spinner-border spinner-border-sm me-1"></span> تنفيذ');
      try {
        const data = await window.postForm(form);
        const ids = (data.ids && data.ids.length) ? data.ids : checked.map((c) => Number(c.value));

        if (data.action === 'delete') {
          ids.forEach((id) => {
            const row = document.querySelector('tr[data-source-id="' + id + '"]');
            if (row) row.remove();
          });
        } else if (data.action === 'auto-on' || data.action === 'auto-off') {
          const on = data.action === 'auto-on';
          ids.forEach((id) => {
            const row = document.querySelector('tr[data-source-id="' + id + '"]');
            const b = row ? row.querySelector('.js-auto-btn') : null;
            if (!b) return;
            b.className = 'btn btn-sm js-auto-btn ' + (on ? 'btn-success' : 'btn-outline-secondary');
            const icon = b.querySelector('i');
            if (icon) icon.className = 'bi ' + (on ? 'bi-broadcast' : 'bi-broadcast-pin');
            const label = b.querySelector('span');
            if (label) label.textContent = on ? ' مفعّل' : ' يدوي';
          });
        }

        // clear the selection, keep the page exactly where it is
        checked.forEach((c) => { c.checked = false; });
        document.querySelectorAll('.source-check').forEach((c) => { c.checked = false; });
        const bar = document.getElementById('bulkBar');
        if (bar) bar.classList.add('d-none');
        const counter = document.getElementById('bulkCount');
        if (counter) counter.textContent = '0';
        const submit = document.getElementById('bulkSubmit');
        if (submit) { submit.disabled = true; if (submit.dataset.label) submit.innerHTML = submit.dataset.label; }

        if (window.adminToast) window.adminToast(data.message || 'تم تنفيذ العملية', 'success');
      } catch (err) {
        busy(btn, false);
        if (window.adminToast) window.adminToast(err.message, 'error');
      }
    });
  }

  function boot() {
    bindPinButtons();
    bindAutoButtons();
    bindDeleteButtons();
    bindBulkForm();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();