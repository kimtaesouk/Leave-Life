(function (global) {
    'use strict';

    const STORAGE_KEY = 'leaveLifeSmsDemoLogs';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizePhone(value) {
        return String(value || '').replace(/[^0-9]/g, '');
    }

    function formatPhone(value) {
        const phone = normalizePhone(value);
        if (phone.length === 11) return phone.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
        if (phone.length === 10) return phone.replace(/(\d{2,3})(\d{3,4})(\d{4})/, '$1-$2-$3');
        return value;
    }

    function estimateLegacyBytes(value) {
        return Array.from(String(value || '')).reduce((total, character) => {
            return total + (character.charCodeAt(0) > 127 ? 2 : 1);
        }, 0);
    }

    function ensureStyles() {
        if (document.getElementById('leaveLifeSmsDemoStyles')) return;
        const style = document.createElement('style');
        style.id = 'leaveLifeSmsDemoStyles';
        style.textContent = `
            .sms-demo-overlay { position: fixed; inset: 0; z-index: 100000; display: grid; place-items: center; padding: 20px; background: rgba(23, 35, 30, .58); backdrop-filter: blur(5px); }
            .sms-demo-dialog { width: min(460px, 100%); max-height: min(760px, calc(100vh - 40px)); overflow: auto; border: 1px solid rgba(31, 78, 58, .12); border-radius: 24px; background: #fff; box-shadow: 0 28px 80px rgba(13, 39, 28, .25); color: #24352e; }
            .sms-demo-head { display: flex; justify-content: space-between; gap: 16px; padding: 24px 24px 18px; border-bottom: 1px solid #e7eee9; }
            .sms-demo-head h2 { margin: 4px 0 0; font-size: 1.25rem; color: #1f4e3a; }
            .sms-demo-kicker { color: #8a6b36; font-size: .75rem; font-weight: 800; letter-spacing: .08em; }
            .sms-demo-close { width: 36px; height: 36px; border: 0; border-radius: 50%; background: #eef4f0; color: #476057; cursor: pointer; font-size: 1.25rem; }
            .sms-demo-body { padding: 22px 24px 8px; }
            .sms-demo-notice { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 18px; padding: 12px 14px; border-radius: 12px; background: #fff7e7; color: #785923; font-size: .82rem; line-height: 1.55; }
            .sms-demo-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; color: #63736c; font-size: .82rem; }
            .sms-demo-type { padding: 5px 9px; border-radius: 999px; background: #e7f1eb; color: #1f6045; font-weight: 800; }
            .sms-demo-phone { padding: 18px 16px; border-radius: 18px; background: #f1f4f2; }
            .sms-demo-phone-label { display: block; margin-bottom: 9px; color: #66766f; font-size: .72rem; font-weight: 800; }
            .sms-demo-bubble { padding: 15px 16px; border-radius: 4px 18px 18px 18px; background: #fff; box-shadow: 0 5px 18px rgba(33, 63, 49, .08); color: #263a31; font-size: .9rem; line-height: 1.65; white-space: pre-wrap; overflow-wrap: anywhere; }
            .sms-demo-attachment { display: grid; grid-template-columns: 82px 1fr; gap: 12px; align-items: center; margin-top: 12px; padding: 11px; border-radius: 14px; background: #fff; box-shadow: 0 5px 18px rgba(33, 63, 49, .08); }
            .sms-demo-document-thumb { aspect-ratio: 1 / 1.35; padding: 8px; border: 1px solid #d8e0db; border-radius: 5px; background: #fff; box-shadow: 0 3px 8px rgba(33, 63, 49, .09); }
            .sms-demo-document-thumb strong { display: block; margin-bottom: 6px; color: #1f4e3a; font-size: .48rem; text-align: center; }
            .sms-demo-document-line { height: 3px; margin: 4px 0; border-radius: 2px; background: #dce5df; }
            .sms-demo-document-line.is-green { width: 68%; background: #7da18f; }
            .sms-demo-attachment-info strong { display: block; margin-bottom: 4px; color: #31473d; font-size: .82rem; }
            .sms-demo-attachment-info span { color: #75847d; font-size: .72rem; line-height: 1.45; }
            .sms-demo-actions { display: grid; grid-template-columns: 1fr 1.35fr; gap: 10px; padding: 20px 24px 24px; }
            .sms-demo-actions button { min-height: 46px; border-radius: 12px; font-weight: 800; cursor: pointer; }
            .sms-demo-cancel { border: 1px solid #cad6cf; background: #fff; color: #52655c; }
            .sms-demo-send { border: 1px solid #1f4e3a; background: #1f4e3a; color: #fff; }
            .sms-demo-send:disabled { cursor: wait; opacity: .72; }
            .sms-demo-success { padding: 38px 26px 34px; text-align: center; }
            .sms-demo-success-icon { display: grid; place-items: center; width: 62px; height: 62px; margin: 0 auto 18px; border-radius: 50%; background: #e2f2e8; color: #1f6a49; font-size: 1.8rem; font-weight: 900; }
            .sms-demo-success h2 { margin: 0 0 10px; color: #1f4e3a; font-size: 1.35rem; }
            .sms-demo-success p { margin: 0; color: #64756d; font-size: .88rem; line-height: 1.65; }
            .sms-demo-success time { display: inline-block; margin-top: 14px; color: #8a6b36; font-size: .78rem; font-weight: 800; }
            .sms-demo-success button { width: 100%; min-height: 46px; margin-top: 24px; border: 0; border-radius: 12px; background: #1f4e3a; color: #fff; font-weight: 800; cursor: pointer; }
            @media (max-width: 520px) {
                .sms-demo-overlay { align-items: end; padding: 0; }
                .sms-demo-dialog { width: 100%; max-height: 88vh; border-radius: 24px 24px 0 0; }
            }
        `;
        document.head.appendChild(style);
    }

    function saveDemoLog(entry) {
        try {
            const current = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            current.unshift(entry);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(current.slice(0, 20)));
        } catch (error) {
            console.warn('SMS demo log could not be saved.', error);
        }
    }

    function createMessage(estimateId, pdfUrl) {
        const link = pdfUrl || `${global.location.origin}/uploads/estimate_docs/estimate_${estimateId}.pdf`;
        return `[리브 라이프]\n요청하신 장례 견적서가 준비되었습니다.\n아래 링크에서 PDF 견적서를 확인해주세요.\n${link}`;
    }

    function showDemo(options) {
        ensureStyles();
        const phone = normalizePhone(options.phone);
        const pdfUrl = options.pdfUrl || '';
        const message = createMessage(options.estimateId, pdfUrl);
        const byteLength = estimateLegacyBytes(message);
        const includeImage = options.includeImage !== false;
        const messageType = includeImage ? 'MMS' : (byteLength > 90 ? 'LMS' : 'SMS');

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'sms-demo-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'smsDemoTitle');
            overlay.innerHTML = `
                <section class="sms-demo-dialog">
                    <header class="sms-demo-head">
                        <div><span class="sms-demo-kicker">SMS DEMO</span><h2 id="smsDemoTitle">문자 전송 미리보기</h2></div>
                        <button type="button" class="sms-demo-close" aria-label="닫기">×</button>
                    </header>
                    <div class="sms-demo-body">
                        <div class="sms-demo-notice"><strong>시연 모드</strong><span>실제 문자와 요금은 발생하지 않습니다. PDF 링크와 견적서 첫 페이지 이미지가 함께 전송되는 화면입니다.</span></div>
                        <div class="sms-demo-meta"><strong>${escapeHtml(formatPhone(phone))}</strong><span class="sms-demo-type">${messageType} · 약 ${byteLength}byte</span></div>
                        <div class="sms-demo-phone"><span class="sms-demo-phone-label">리브 라이프 장례 서비스 운영센터</span><div class="sms-demo-bubble">${escapeHtml(message)}</div>${includeImage ? `<div class="sms-demo-attachment"><div class="sms-demo-document-thumb"><strong>리브 라이프 견적서</strong><div class="sms-demo-document-line is-green"></div><div class="sms-demo-document-line"></div><div class="sms-demo-document-line"></div><div class="sms-demo-document-line"></div><div class="sms-demo-document-line is-green"></div><div class="sms-demo-document-line"></div><div class="sms-demo-document-line"></div></div><div class="sms-demo-attachment-info"><strong>견적서 1페이지 이미지</strong><span>estimate_${escapeHtml(options.estimateId)}.jpg<br>PDF 원본은 위 링크에서 확인</span></div></div>` : ''}</div>
                    </div>
                    <footer class="sms-demo-actions">
                        <button type="button" class="sms-demo-cancel">취소</button>
                        <button type="button" class="sms-demo-send">시연 발송</button>
                    </footer>
                </section>`;

            const close = (result) => {
                document.removeEventListener('keydown', onKeyDown);
                overlay.remove();
                resolve(result);
            };
            const onKeyDown = (event) => {
                if (event.key === 'Escape') close({ success: false, cancelled: true, demo: true });
            };
            overlay.querySelector('.sms-demo-close').addEventListener('click', () => close({ success: false, cancelled: true, demo: true }));
            overlay.querySelector('.sms-demo-cancel').addEventListener('click', () => close({ success: false, cancelled: true, demo: true }));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) close({ success: false, cancelled: true, demo: true });
            });
            overlay.querySelector('.sms-demo-send').addEventListener('click', () => {
                const button = overlay.querySelector('.sms-demo-send');
                button.disabled = true;
                button.textContent = '발송 처리 중…';
                global.setTimeout(() => {
                    const sentAt = new Date();
                    const entry = {
                        estimateId: Number(options.estimateId),
                        phone,
                        message,
                        messageType,
                        byteLength,
                        imageAttached: includeImage,
                        sentAt: sentAt.toISOString(),
                        demo: true
                    };
                    saveDemoLog(entry);
                    overlay.querySelector('.sms-demo-dialog').innerHTML = `
                        <div class="sms-demo-success">
                            <div class="sms-demo-success-icon">✓</div>
                            <h2>시연 발송 완료</h2>
                            <p>${escapeHtml(formatPhone(phone))} 번호로 발송되는 과정을 확인했습니다.<br>실제 문자는 전송되지 않았습니다.</p>
                            <time>${escapeHtml(sentAt.toLocaleString('ko-KR'))}</time>
                            <button type="button" class="sms-demo-done">확인</button>
                        </div>`;
                    overlay.querySelector('.sms-demo-done').addEventListener('click', () => close({ success: true, demo: true, entry }));
                }, 650);
            });
            document.addEventListener('keydown', onKeyDown);
            document.body.appendChild(overlay);
            overlay.querySelector('.sms-demo-send').focus();
        });
    }

    async function send(options) {
        const phone = normalizePhone(options.phone);
        if (!options.estimateId) throw new Error('견적서 ID가 필요합니다.');
        if (phone.length < 9 || phone.length > 11) throw new Error('전화번호를 확인해주세요.');

        if (options.demoMode !== false) {
            return showDemo(Object.assign({}, options, { phone }));
        }

        const formData = new FormData();
        formData.append('estimate_id', String(options.estimateId));
        formData.append('phone', phone);
        const response = await fetch(options.apiUrl || '../api/send_estimate_sms.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || '문자 전송에 실패했습니다.');
        return data;
    }

    global.LeaveLifeSms = { send, createMessage, getDemoLogs: () => JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]') };
})(window);
