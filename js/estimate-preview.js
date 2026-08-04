(function (global) {
    'use strict';

    function canvasToBlob(canvas, mimeType, quality) {
        return new Promise((resolve) => canvas.toBlob(resolve, mimeType, quality));
    }

    async function createJpegBlob(template, options) {
        const settings = Object.assign({ maxWidth: 900, quality: 0.78, scale: 1.5 }, options || {});
        if (!template || typeof global.html2canvas !== 'function') return null;
        const page = template.querySelector('.estimate-pdf-page');
        if (!page) return null;

        template.style.display = 'block';
        try {
            const sourceCanvas = await global.html2canvas(page, {
                scale: settings.scale,
                useCORS: true,
                backgroundColor: '#ffffff'
            });
            const ratio = Math.min(1, settings.maxWidth / sourceCanvas.width);
            const outputCanvas = document.createElement('canvas');
            outputCanvas.width = Math.max(1, Math.round(sourceCanvas.width * ratio));
            outputCanvas.height = Math.max(1, Math.round(sourceCanvas.height * ratio));
            const context = outputCanvas.getContext('2d');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, outputCanvas.width, outputCanvas.height);
            context.drawImage(sourceCanvas, 0, 0, outputCanvas.width, outputCanvas.height);
            return await canvasToBlob(outputCanvas, 'image/jpeg', settings.quality);
        } catch (error) {
            console.error('Estimate preview image error:', error);
            return null;
        } finally {
            template.style.display = 'none';
        }
    }

    global.EstimatePreview = { createJpegBlob };
})(window);
