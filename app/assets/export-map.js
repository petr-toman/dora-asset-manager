(function () {
    function graphToast(message) {
        if (typeof toast === 'function') toast(message);
        else console.log(message);
    }

    function visibleExportElements() {
        if (!cy) return null;
        const visibleNodes = cy.nodes().filter(n => n.visible() && !n.hasClass('hiddenByFilter'));
        const visibleNodeIds = new Set(visibleNodes.map(n => n.id()));
        const visibleEdges = cy.edges().filter(e => e.visible() && !e.hasClass('hiddenByFilter') && visibleNodeIds.has(e.source().id()) && visibleNodeIds.has(e.target().id()));
        return visibleNodes.union(visibleEdges);
    }

    function exportMapPng() {
        if (!cy) return;
        const eles = visibleExportElements();
        if (!eles || eles.nodes().length === 0) {
            graphToast('Není co exportovat.');
            return;
        }
        const dataUrl = cy.png({
            output: 'blob-promise',
            full: false,
            scale: 3,
            bg: '#ffffff',
            eles
        });
        Promise.resolve(dataUrl).then(blob => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `dora-asset-map-${new Date().toISOString().slice(0, 10)}.png`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(a.href);
            graphToast('Mapa exportována do PNG');
        }).catch(err => graphToast('Export PNG selhal: ' + err.message));
    }

    async function exportMapPdf() {
        if (!cy) return;
        const eles = visibleExportElements();
        if (!eles || eles.nodes().length === 0) {
            graphToast('Není co exportovat.');
            return;
        }
        if (!window.jspdf?.jsPDF) {
            graphToast('PDF knihovna není načtená.');
            return;
        }
        try {
            const png = cy.png({
                output: 'base64uri',
                full: false,
                scale: 3,
                bg: '#ffffff',
                eles
            });
            const pdf = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a3' });
            const pageW = pdf.internal.pageSize.getWidth();
            const pageH = pdf.internal.pageSize.getHeight();
            const margin = 8;
            const imgProps = pdf.getImageProperties(png);
            const usableW = pageW - margin * 2;
            const usableH = pageH - margin * 2;
            const ratio = Math.min(usableW / imgProps.width, usableH / imgProps.height);
            const imgW = imgProps.width * ratio;
            const imgH = imgProps.height * ratio;
            const x = (pageW - imgW) / 2;
            const y = (pageH - imgH) / 2;
            pdf.addImage(png, 'PNG', x, y, imgW, imgH, undefined, 'FAST');
            pdf.save(`dora-asset-map-${new Date().toISOString().slice(0, 10)}.pdf`);
            graphToast('Mapa exportována do PDF A3');
        } catch (err) {
            graphToast('Export PDF selhal: ' + err.message);
        }
    }

    function bindMapExportButtons() {
        document.querySelector('#btnExportMapPng')?.addEventListener('click', exportMapPng);
        document.querySelector('#btnExportMapPdf')?.addEventListener('click', exportMapPdf);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindMapExportButtons);
    else bindMapExportButtons();
})();
