import { useEffect, useRef, useState } from 'react';

export function PdfPreview({ url, className, firstPageOnly = false, loadingClassName }) {
    const containerRef = useRef(null);
    const [status, setStatus] = useState('loading');
    const [errorMessage, setErrorMessage] = useState('');

    useEffect(() => {
        let cancelled = false;
        let loadingTask = null;
        const renderTasks = [];
        setStatus('loading');

        async function render() {
            try {
                const [pdfjsLib, workerModule] = await Promise.all([
                    import('pdfjs-dist'),
                    import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
                ]);
                if (cancelled) return;

                pdfjsLib.GlobalWorkerOptions.workerSrc = workerModule.default;
                loadingTask = pdfjsLib.getDocument({ url, withCredentials: true });
                const pdf = await loadingTask.promise;
                if (cancelled) return;

                const container = containerRef.current;
                if (!container) return;
                container.innerHTML = '';

                const pageCount = firstPageOnly ? 1 : pdf.numPages;
                for (let pageNum = 1; pageNum <= pageCount; pageNum++) {
                    const page = await pdf.getPage(pageNum);
                    if (cancelled) return;

                    const viewport = page.getViewport({ scale: firstPageOnly ? 0.5 : 1.5 });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.className = firstPageOnly
                        ? 'h-full w-full rounded-lg object-cover'
                        : 'mb-2 w-full rounded-lg border border-border last:mb-0';
                    container.appendChild(canvas);

                    const renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport });
                    renderTasks.push(renderTask);
                    await renderTask.promise;
                }

                if (!cancelled) setStatus('ready');
            } catch (err) {
                console.error('[PdfPreview]', err);
                if (!cancelled) {
                    setErrorMessage(err?.message ?? String(err));
                    setStatus('error');
                }
            }
        }

        render();

        return () => {
            cancelled = true;
            renderTasks.forEach((task) => task.cancel());
            void loadingTask?.destroy();
        };
    }, [url, firstPageOnly]);

    if (status === 'error') {
        return (
            <p className="p-2 text-xs text-red-600">
                Failed to load preview{errorMessage ? `: ${errorMessage}` : '.'}
            </p>
        );
    }

    return (
        <div className={className}>
            {status === 'loading' && (
                <div className={loadingClassName ?? 'flex h-full w-full items-center justify-center'}>
                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                </div>
            )}
            <div ref={containerRef} className={status === 'loading' ? 'hidden' : undefined} />
        </div>
    );
}
