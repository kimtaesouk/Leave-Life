(function (global) {
    'use strict';

    const STORAGE_KEY = 'leaveLifeEmailDemoLogs';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
    }

    function ensureStyles() {
        if (document.getElementById('leaveLifeEmailDemoStyles')) return;
        const style = document.createElement('style');
        style.id = 'leaveLifeEmailDemoStyles';
        style.textContent = `
            .email-demo-overlay { position: fixed; inset: 0; z-index: 100001; display: grid; place-items: center; padding: 20px; background: rgba(23, 35, 30, .6); backdrop-filter: blur(5px); }
            .email-demo-dialog { width: min(570px, 100%); max-height: min(820px, calc(100vh - 40px)); overflow: auto; border: 1px solid rgba(31, 78, 58, .12); border-radius: 24px; background: #fff; box-shadow: 0 28px 80px rgba(13, 39, 28, .25); color: #24352e; }
            .email-demo-head { display: flex; justify-content: space-between; gap: 16px; padding: 23px 24px 17px; border-bottom: 1px solid #e7eee9; }
            .email-demo-head h2 { margin: 4px 0 0; font-size: 1.25rem; color: #1f4e3a; }
            .email-demo-kicker { color: #8a6b36; font-size: .75rem; font-weight: 800; letter-spacing: .08em; }
            .email-demo-close { width: 36px; height: 36px; border: 0; border-radius: 50%; background: #eef4f0; color: #476057; cursor: pointer; font-size: 1.25rem; }
            .email-demo-body { padding: 20px 24px 8px; }
            .email-demo-notice { margin-bottom: 16px; padding: 11px 13px; border-radius: 12px; background: #fff7e7; color: #765924; font-size: .8rem; line-height: 1.55; }
            .email-demo-mail { overflow: hidden; border: 1px solid #dce5df; border-radius: 16px; background: #f7f9f8; }
            .email-demo-meta { display: grid; gap: 8px; padding: 15px 17px; border-bottom: 1px solid #dce5df; background: #fff; font-size: .8rem; }
            .email-demo-meta div { display: grid; grid-template-columns: 48px 1fr; gap: 9px; }
            .email-demo-meta span { color: #7c8a83; font-weight: 700; }
            .email-demo-meta strong { color: #34493f; overflow-wrap: anywhere; }
            .email-demo-content { padding: 22px 20px; color: #42554c; font-size: .86rem; line-height: 1.75; }
            .email-demo-brand { margin-bottom: 20px; color: #1f4e3a; font-size: 1.05rem; font-weight: 900; }
            .email-demo-content p { margin: 0 0 13px; }
            .email-demo-attachment { display: flex; align-items: center; gap: 12px; margin-top: 20px; padding: 13px; border: 1px solid #d9e3dd; border-radius: 12px; background: #fff; }
            .email-demo-file-icon { display: grid; place-items: center; width: 42px; height: 50px; border-radius: 6px; background: #a5534d; color: #fff; font-size: .68rem; font-weight: 900; flex: 0 0 auto; }
            .email-demo-file-icon.is-image { background: #52745f; }
            .email-demo-attachment-list { display: grid; gap: 9px; margin-top: 20px; }
            .email-demo-inline-label { margin: 22px 0 10px; color: #1f4e3a; font-weight: 900; }
            .email-demo-inline-preview { overflow: hidden; min-height: 220px; border: 1px solid #d7e1db; border-radius: 12px; background: #eef3f0; }
            .email-demo-inline-preview img { display: block; width: 100%; height: auto; }
            .email-demo-inline-placeholder { display: grid; place-items: center; min-height: 220px; padding: 24px; color: #547064; text-align: center; background: linear-gradient(145deg, #f9fbfa, #e8f0eb); }
            .email-demo-inline-placeholder strong { display: block; margin-bottom: 6px; color: #1f4e3a; font-size: 1rem; }
            .email-demo-attachment strong { display: block; margin-bottom: 3px; color: #30453a; font-size: .82rem; }
            .email-demo-attachment span { color: #7a8982; font-size: .72rem; }
            .email-demo-actions { display: grid; grid-template-columns: 1fr 1.35fr; gap: 10px; padding: 20px 24px 24px; }
            .email-demo-actions button { min-height: 46px; border-radius: 12px; font-weight: 800; cursor: pointer; }
            .email-demo-cancel { border: 1px solid #cad6cf; background: #fff; color: #52655c; }
            .email-demo-send { border: 1px solid #1f4e3a; background: #1f4e3a; color: #fff; }
            .email-demo-send:disabled { cursor: wait; opacity: .72; }
            .email-demo-success { padding: 40px 28px 34px; text-align: center; }
            .email-demo-success-icon { display: grid; place-items: center; width: 62px; height: 62px; margin: 0 auto 18px; border-radius: 50%; background: #e2f2e8; color: #1f6a49; font-size: 1.8rem; font-weight: 900; }
            .email-demo-success h2 { margin: 0 0 10px; color: #1f4e3a; font-size: 1.35rem; }
            .email-demo-success p { margin: 0; color: #64756d; font-size: .88rem; line-height: 1.65; }
            .email-demo-success button { width: 100%; min-height: 46px; margin-top: 24px; border: 0; border-radius: 12px; background: #1f4e3a; color: #fff; font-weight: 800; cursor: pointer; }
            @media (max-width: 600px) { .email-demo-overlay { align-items: end; padding: 0; } .email-demo-dialog { width: 100%; max-height: 90vh; border-radius: 24px 24px 0 0; } }
        `;
        document.head.appendChild(style);
    }

    function saveLog(entry) {
        try {
            const current = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            current.unshift(entry);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(current.slice(0, 20)));
        } catch (error) {
            console.warn('Email demo log could not be saved.', error);
        }
    }

    function showDemo(options) {
        ensureStyles();
        const email = String(options.email || '').trim();
        const subject = `[리브 라이프] 요청하신 견적서가 준비되었습니다.`;
        const previewImageUrl = String(options.previewImageUrl || '').trim();
        const inlinePreview = previewImageUrl
            ? `<div class="email-demo-inline-preview"><img src="${escapeHtml(previewImageUrl)}" alt="견적서 미리보기"></div>`
            : `<div class="email-demo-inline-preview"><div class="email-demo-inline-placeholder"><div><strong>견적서 미리보기</strong>실제 메일에서는 견적서 첫 페이지가 이 위치에 표시됩니다.</div></div></div>`;
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'email-demo-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'emailDemoTitle');
            overlay.innerHTML = `
                <section class="email-demo-dialog">
                    <header class="email-demo-head"><div><span class="email-demo-kicker">EMAIL DEMO</span><h2 id="emailDemoTitle">이메일 전송 미리보기</h2></div><button type="button" class="email-demo-close" aria-label="닫기">×</button></header>
                    <div class="email-demo-body">
                        <div class="email-demo-notice"><strong>시연 모드</strong> · 실제 이메일은 전송되지 않습니다. 본문 미리보기와 PDF 첨부 구성을 확인합니다.</div>
                        <article class="email-demo-mail">
                            <div class="email-demo-meta"><div><span>받는 사람</span><strong>${escapeHtml(email)}</strong></div><div><span>제목</span><strong>${escapeHtml(subject)}</strong></div></div>
                            <div class="email-demo-content"><div class="email-demo-brand">리브 라이프</div><p>안녕하세요.<br>마음을 먼저 헤아리는 장례 동행 서비스, 리브 라이프입니다.</p><p>상담을 통해 요청하신 장례 서비스 견적서가 준비되어 보내드립니다. 아래에서 견적서 내용을 바로 확인하실 수 있으며, 상세 PDF 원본은 메일에 첨부했습니다.</p><div class="email-demo-inline-label">견적서 미리보기</div>${inlinePreview}<p style="margin-top:20px;">견적 금액은 실제 장례 진행 상황과 선택 항목에 따라 달라질 수 있습니다. 궁금하신 사항은 담당 상담사에게 편하게 문의해주세요.</p><div class="email-demo-attachment-list"><div class="email-demo-attachment"><div class="email-demo-file-icon">PDF</div><div><strong>리브라이프_견적서_${escapeHtml(options.estimateId)}.pdf</strong><span>상세 견적서 PDF</span></div></div></div></div>
                        </article>
                    </div>
                    <footer class="email-demo-actions"><button type="button" class="email-demo-cancel">취소</button><button type="button" class="email-demo-send">시연 발송</button></footer>
                </section>`;

            const close = (result) => { overlay.remove(); resolve(result); };
            overlay.querySelector('.email-demo-close').addEventListener('click', () => close({ success: false, cancelled: true, demo: true }));
            overlay.querySelector('.email-demo-cancel').addEventListener('click', () => close({ success: false, cancelled: true, demo: true }));
            overlay.addEventListener('click', (event) => { if (event.target === overlay) close({ success: false, cancelled: true, demo: true }); });
            overlay.querySelector('.email-demo-send').addEventListener('click', () => {
                const button = overlay.querySelector('.email-demo-send');
                button.disabled = true;
                button.textContent = '발송 처리 중…';
                global.setTimeout(() => {
                    const sentAt = new Date();
                    const entry = { estimateId: Number(options.estimateId), email, subject, sentAt: sentAt.toISOString(), demo: true };
                    saveLog(entry);
                    overlay.querySelector('.email-demo-dialog').innerHTML = `<div class="email-demo-success"><div class="email-demo-success-icon">✓</div><h2>시연 이메일 발송 완료</h2><p>${escapeHtml(email)} 주소로 본문 미리보기와 PDF 견적서를 발송하는 과정을 확인했습니다.<br>실제 이메일은 전송되지 않았습니다.</p><button type="button" class="email-demo-done">확인</button></div>`;
                    overlay.querySelector('.email-demo-done').addEventListener('click', () => close({ success: true, demo: true, entry }));
                }, 650);
            });
            document.body.appendChild(overlay);
            overlay.querySelector('.email-demo-send').focus();
        });
    }

    async function send(options) {
        const email = String(options.email || '').trim();
        if (!options.estimateId) throw new Error('견적서 ID가 필요합니다.');
        if (!isEmail(email)) throw new Error('이메일 주소를 확인해주세요.');
        if (options.demoMode !== false) return showDemo(Object.assign({}, options, { email }));

        const formData = new FormData();
        formData.append('estimate_id', String(options.estimateId));
        const response = await fetch(options.apiUrl || '../api/send_estimate_email.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || '이메일 전송에 실패했습니다.');
        return data;
    }

    global.LeaveLifeEmail = { send, getDemoLogs: () => JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]') };
})(window);
