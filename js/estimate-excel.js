(function (global) {
    'use strict';

    const EXCEL_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    const COLORS = {
        brand: '174D3D',
        brandSoft: 'EAF2EE',
        line: 'D8E1DC',
        label: '5F6E67',
        text: '25332D',
        muted: '718078',
        white: 'FFFFFF',
        warm: 'F7F4EE'
    };

    function money(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? Math.max(0, Math.floor(parsed)) : 0;
    }

    function isFuneralProduct(item) {
        return String(item && item.category || '').replace(/\s+/g, '') === '상조상품';
    }

    function calculatePriceSummary(items) {
        const safeItems = Array.isArray(items) ? items : [];
        const funeralProductAmount = safeItems
            .filter(isFuneralProduct)
            .reduce((sum, item) => sum + money(item.amount), 0);
        const funeralHallAmount = safeItems
            .filter(item => !isFuneralProduct(item))
            .reduce((sum, item) => sum + money(item.amount), 0);

        return {
            funeralProductAmount,
            funeralHallAmount,
            totalAmount: funeralProductAmount + funeralHallAmount
        };
    }

    function thinBorder() {
        const side = { style: 'thin', color: { argb: COLORS.line } };
        return { top: side, left: side, bottom: side, right: side };
    }

    function setCellFont(cell, options) {
        cell.font = {
            name: '맑은 고딕',
            size: options.size || 10,
            bold: Boolean(options.bold),
            color: { argb: options.color || COLORS.text }
        };
    }

    function styleSectionTitle(worksheet, rowNumber, title) {
        worksheet.mergeCells(`A${rowNumber}:E${rowNumber}`);
        const cell = worksheet.getCell(`A${rowNumber}`);
        cell.value = title;
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: COLORS.brandSoft } };
        cell.alignment = { vertical: 'middle', horizontal: 'left' };
        cell.border = thinBorder();
        setCellFont(cell, { size: 11, bold: true, color: COLORS.brand });
        worksheet.getRow(rowNumber).height = 25;
    }

    function styleInfoLabel(cell) {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: COLORS.warm } };
        cell.alignment = { vertical: 'middle', horizontal: 'left' };
        cell.border = thinBorder();
        setCellFont(cell, { size: 9, bold: true, color: COLORS.label });
    }

    function styleInfoValue(cell) {
        cell.alignment = { vertical: 'middle', horizontal: 'left', wrapText: true };
        cell.border = thinBorder();
        setCellFont(cell, { size: 9, color: COLORS.text });
    }

    function addInfoRow(worksheet, rowNumber, leftLabel, leftValue, rightLabel, rightValue) {
        worksheet.mergeCells(`B${rowNumber}:C${rowNumber}`);
        worksheet.getCell(`A${rowNumber}`).value = leftLabel;
        worksheet.getCell(`B${rowNumber}`).value = leftValue || '-';
        worksheet.getCell(`D${rowNumber}`).value = rightLabel;
        worksheet.getCell(`E${rowNumber}`).value = rightValue || '-';
        styleInfoLabel(worksheet.getCell(`A${rowNumber}`));
        styleInfoValue(worksheet.getCell(`B${rowNumber}`));
        styleInfoLabel(worksheet.getCell(`D${rowNumber}`));
        styleInfoValue(worksheet.getCell(`E${rowNumber}`));
        worksheet.getRow(rowNumber).height = 24;
    }

    function addWideInfoRow(worksheet, rowNumber, label, value) {
        worksheet.mergeCells(`B${rowNumber}:E${rowNumber}`);
        worksheet.getCell(`A${rowNumber}`).value = label;
        worksheet.getCell(`B${rowNumber}`).value = value || '-';
        styleInfoLabel(worksheet.getCell(`A${rowNumber}`));
        styleInfoValue(worksheet.getCell(`B${rowNumber}`));
        worksheet.getRow(rowNumber).height = 24;
    }

    async function createBlob(options) {
        if (!global.ExcelJS || !global.ExcelJS.Workbook) {
            throw new Error('엑셀 생성 라이브러리를 불러오지 못했습니다.');
        }

        const estimateInfo = options && options.estimateInfo ? options.estimateInfo : {};
        const items = options && Array.isArray(options.items) ? options.items : [];
        const summary = options && options.priceSummary
            ? options.priceSummary
            : calculatePriceSummary(items);
        const workbook = new global.ExcelJS.Workbook();
        workbook.creator = '리브 라이프';
        workbook.company = '리브 라이프';
        workbook.subject = '장례 서비스 견적서';
        workbook.created = new Date();
        workbook.modified = new Date();

        const worksheet = workbook.addWorksheet('견적서', {
            views: [{ showGridLines: false }],
            pageSetup: {
                paperSize: 9,
                orientation: 'portrait',
                fitToPage: true,
                fitToWidth: 1,
                fitToHeight: 1,
                margins: { left: 0.35, right: 0.35, top: 0.45, bottom: 0.45, header: 0.2, footer: 0.2 }
            }
        });

        worksheet.columns = [
            { key: 'category', width: 18 },
            { key: 'name', width: 30 },
            { key: 'qty', width: 11 },
            { key: 'unitPrice', width: 18 },
            { key: 'amount', width: 18 }
        ];

        worksheet.mergeCells('A1:E1');
        worksheet.getCell('A1').value = '견적서';
        worksheet.getCell('A1').alignment = { vertical: 'middle', horizontal: 'left' };
        setCellFont(worksheet.getCell('A1'), { size: 22, bold: true, color: COLORS.brand });
        worksheet.getRow(1).height = 34;

        worksheet.mergeCells('A2:E2');
        worksheet.getCell('A2').value = '리브 라이프 장례 서비스 견적';
        worksheet.getCell('A2').alignment = { vertical: 'middle', horizontal: 'left' };
        setCellFont(worksheet.getCell('A2'), { size: 10, color: COLORS.muted });
        worksheet.getRow(2).height = 20;

        addInfoRow(
            worksheet,
            4,
            '작성일',
            estimateInfo['작성일'] || '-',
            '견적번호',
            estimateInfo['견적번호'] || '-'
        );

        styleSectionTitle(worksheet, 6, '기본 정보');
        const personLabel = Object.prototype.hasOwnProperty.call(estimateInfo, '환우명') ? '환우명' : '고인 성함';
        addInfoRow(
            worksheet,
            7,
            personLabel,
            estimateInfo[personLabel] || '-',
            '담당자 성함',
            estimateInfo['담당자 성함'] || '-'
        );
        addInfoRow(
            worksheet,
            8,
            '연락처',
            estimateInfo['연락처'] || '-',
            '이메일',
            estimateInfo['이메일'] || '-'
        );
        addInfoRow(
            worksheet,
            9,
            '장례 기간',
            estimateInfo['장례 기간'] || '-',
            '지역',
            estimateInfo['지역'] || '-'
        );
        addWideInfoRow(worksheet, 10, '장례식장', estimateInfo['장례식장'] || '-');
        addWideInfoRow(worksheet, 11, '장례식장 주소', estimateInfo['장례식장 주소'] || '-');

        styleSectionTitle(worksheet, 13, '선택 항목');
        const headerRow = worksheet.getRow(14);
        headerRow.values = ['카테고리', '품명', '개수', '단가', '금액'];
        headerRow.height = 25;
        headerRow.eachCell(cell => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: COLORS.brand } };
            cell.alignment = { vertical: 'middle', horizontal: 'center' };
            cell.border = thinBorder();
            setCellFont(cell, { size: 9, bold: true, color: COLORS.white });
        });

        const firstItemRow = 15;
        if (items.length === 0) {
            worksheet.mergeCells(`A${firstItemRow}:E${firstItemRow}`);
            worksheet.getCell(`A${firstItemRow}`).value = '선택된 항목이 없습니다.';
            worksheet.getCell(`A${firstItemRow}`).alignment = { vertical: 'middle', horizontal: 'center' };
            worksheet.getCell(`A${firstItemRow}`).border = thinBorder();
            setCellFont(worksheet.getCell(`A${firstItemRow}`), { size: 9, color: COLORS.muted });
        } else {
            items.forEach((item, index) => {
                const row = worksheet.getRow(firstItemRow + index);
                row.values = [
                    item.category || '-',
                    item.productName || '-',
                    money(item.qty || 1),
                    money(item.unitPrice),
                    money(item.amount)
                ];
                row.height = 23;
                row.eachCell((cell, columnNumber) => {
                    cell.border = thinBorder();
                    cell.alignment = {
                        vertical: 'middle',
                        horizontal: columnNumber >= 3 ? 'right' : 'left',
                        wrapText: columnNumber <= 2
                    };
                    setCellFont(cell, { size: 9, color: COLORS.text });
                });
                row.getCell(3).numFmt = '#,##0';
                row.getCell(4).numFmt = '#,##0"원"';
                row.getCell(5).numFmt = '#,##0"원"';
            });
        }

        const lastItemRow = firstItemRow + Math.max(items.length, 1) - 1;
        const summaryTitleRow = lastItemRow + 2;
        styleSectionTitle(worksheet, summaryTitleRow, '금액 요약');

        const summaryRows = [
            ['상조 상품 금액', money(summary.funeralProductAmount)],
            ['장례식장 금액', money(summary.funeralHallAmount)]
        ];
        summaryRows.forEach((entry, index) => {
            const rowNumber = summaryTitleRow + 1 + index;
            worksheet.mergeCells(`A${rowNumber}:C${rowNumber}`);
            worksheet.mergeCells(`D${rowNumber}:E${rowNumber}`);
            worksheet.getCell(`A${rowNumber}`).value = entry[0];
            worksheet.getCell(`D${rowNumber}`).value = entry[1];
            styleInfoLabel(worksheet.getCell(`A${rowNumber}`));
            styleInfoValue(worksheet.getCell(`D${rowNumber}`));
            worksheet.getCell(`D${rowNumber}`).alignment = { vertical: 'middle', horizontal: 'right' };
            worksheet.getCell(`D${rowNumber}`).numFmt = '#,##0"원"';
            worksheet.getRow(rowNumber).height = 24;
        });

        const totalRowNumber = summaryTitleRow + 3;
        worksheet.mergeCells(`A${totalRowNumber}:C${totalRowNumber}`);
        worksheet.mergeCells(`D${totalRowNumber}:E${totalRowNumber}`);
        worksheet.getCell(`A${totalRowNumber}`).value = '최종 합계';
        worksheet.getCell(`D${totalRowNumber}`).value = {
            formula: `D${summaryTitleRow + 1}+D${summaryTitleRow + 2}`,
            result: money(summary.totalAmount)
        };
        [worksheet.getCell(`A${totalRowNumber}`), worksheet.getCell(`D${totalRowNumber}`)].forEach(cell => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: COLORS.brand } };
            cell.border = thinBorder();
            setCellFont(cell, { size: 11, bold: true, color: COLORS.white });
        });
        worksheet.getCell(`D${totalRowNumber}`).alignment = { vertical: 'middle', horizontal: 'right' };
        worksheet.getCell(`D${totalRowNumber}`).numFmt = '#,##0"원"';
        worksheet.getRow(totalRowNumber).height = 28;

        const footerRow = totalRowNumber + 2;
        worksheet.mergeCells(`A${footerRow}:E${footerRow}`);
        worksheet.getCell(`A${footerRow}`).value = '본 견적서는 참고용이며 실제 비용은 현장 상황에 따라 변동될 수 있습니다.';
        worksheet.getCell(`A${footerRow}`).alignment = { vertical: 'middle', horizontal: 'left', wrapText: true };
        setCellFont(worksheet.getCell(`A${footerRow}`), { size: 8, color: COLORS.muted });
        worksheet.getRow(footerRow).height = 22;

        worksheet.autoFilter = { from: `A14`, to: `E${lastItemRow}` };
        worksheet.pageSetup.printArea = `A1:E${footerRow}`;
        worksheet.headerFooter.oddFooter = '리브 라이프 | &P / &N';

        const buffer = await workbook.xlsx.writeBuffer();
        return {
            blob: new Blob([buffer], { type: EXCEL_MIME }),
            ext: 'xlsx',
            priceSummary: summary
        };
    }

    global.EstimateExcel = {
        calculatePriceSummary,
        createBlob
    };
})(typeof window !== 'undefined' ? window : globalThis);
