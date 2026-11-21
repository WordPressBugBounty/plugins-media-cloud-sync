import React from 'react';
import { toast } from 'react-toastify';
import style from './style.scss';
import closeIcon from '../../images/icons/close.svg';
import { copyToClipboard } from '../../helper';
import {
	ZoomInIcon,
	ZoomOutIcon,
	RotateLeftIcon,
	RotateRightIcon,
	ResetIcon,
	DownloadIcon,
	ExternalLinkIcon,
	MediaLibraryIcon,
	CopyIcon,
	CloseIcon
} from './icons';

const MIN_ZOOM = 0.5;
const MAX_ZOOM = 4;
const ZOOM_STEP = 0.25;

const clampZoom = (value) => Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, +value.toFixed(2)));

export default function ImagePreview({
	open,
	src,
	alt = '',
	mediaLibraryUrl = null,
	onClose
}) {
	const [zoom, setZoom] = React.useState(1);
	const [rotation, setRotation] = React.useState(0);
	const [pan, setPan] = React.useState({ x: 0, y: 0 });
	const [dragging, setDragging] = React.useState(false);
	const dragRef = React.useRef(null);

	// A fresh image (or a fresh open) always starts at a clean, un-zoomed view.
	React.useEffect(() => {
		setZoom(1);
		setRotation(0);
		setPan({ x: 0, y: 0 });
	}, [src, open]);

	React.useEffect(() => {
		if (!open) return undefined;
		const onKeyDown = (e) => {
			if (e.key === 'Escape') onClose();
			if (e.key === '+' || e.key === '=') setZoom((z) => clampZoom(z + ZOOM_STEP));
			if (e.key === '-') setZoom((z) => clampZoom(z - ZOOM_STEP));
		};
		window.addEventListener('keydown', onKeyDown);
		return () => window.removeEventListener('keydown', onKeyDown);
	}, [open, onClose]);

	// Lock background scroll while the lightbox is open — wheel events over the overlay
	// itself are already consumed for zoom, but the native scrollbar could still move the
	// page underneath without this.
	React.useEffect(() => {
		if (!open) return undefined;
		const previousOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		return () => {
			document.body.style.overflow = previousOverflow;
		};
	}, [open]);

	if (!open) {
		return null;
	}

	const zoomIn = () => setZoom((z) => clampZoom(z + ZOOM_STEP));
	const zoomOut = () => setZoom((z) => clampZoom(z - ZOOM_STEP));
	const rotateLeft = () => setRotation((r) => (r - 90 + 360) % 360);
	const rotateRight = () => setRotation((r) => (r + 90) % 360);
	const reset = () => {
		setZoom(1);
		setRotation(0);
		setPan({ x: 0, y: 0 });
	};

	const onWheel = (e) => {
		e.preventDefault();
		setZoom((z) => clampZoom(z + (e.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP)));
	};

	// Panning only makes sense once zoomed in past the image's natural size.
	const onMouseDown = (e) => {
		if (zoom <= 1) return;
		setDragging(true);
		dragRef.current = {
			startX: e.clientX,
			startY: e.clientY,
			originX: pan.x,
			originY: pan.y
		};
	};
	const onMouseMove = (e) => {
		if (!dragRef.current) return;
		setPan({
			x: dragRef.current.originX + (e.clientX - dragRef.current.startX),
			y: dragRef.current.originY + (e.clientY - dragRef.current.startY)
		});
	};
	const stopDragging = () => {
		dragRef.current = null;
		setDragging(false);
	};

	const openInNewTab = () => {
		window.open(src, '_blank', 'noopener,noreferrer');
	};

	const copyUrl = () => {
		copyToClipboard(src).then(() => {
			toast.success(
				wp.i18n.__('Copied to clipboard', 'media-cloud-sync'),
				{ autoClose: 1500 }
			);
		});
	};

	return (
		<div
			className={style.overlay}
			onMouseDown={(e) => {
				if (e.target === e.currentTarget) onClose();
			}}
			onWheel={onWheel}
		>
			<div className={style.toolbar} onMouseDown={(e) => e.stopPropagation()}>
				<button
					type="button"
					onClick={zoomOut}
					disabled={zoom <= MIN_ZOOM}
					title={wp.i18n.__('Zoom out', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Zoom out', 'media-cloud-sync')}
				>
					<ZoomOutIcon />
				</button>
				<span className={style.zoomLabel}>{Math.round(zoom * 100)}%</span>
				<button
					type="button"
					onClick={zoomIn}
					disabled={zoom >= MAX_ZOOM}
					title={wp.i18n.__('Zoom in', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Zoom in', 'media-cloud-sync')}
				>
					<ZoomInIcon />
				</button>
				<span className={style.divider} />
				<button
					type="button"
					onClick={rotateLeft}
					title={wp.i18n.__('Rotate left', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Rotate left', 'media-cloud-sync')}
				>
					<RotateLeftIcon />
				</button>
				<button
					type="button"
					onClick={rotateRight}
					title={wp.i18n.__('Rotate right', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Rotate right', 'media-cloud-sync')}
				>
					<RotateRightIcon />
				</button>
				<button
					type="button"
					onClick={reset}
					title={wp.i18n.__('Reset', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Reset', 'media-cloud-sync')}
				>
					<ResetIcon />
				</button>
				<span className={style.divider} />
				<button
					type="button"
					onClick={openInNewTab}
					title={wp.i18n.__('Open in new tab', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Open in new tab', 'media-cloud-sync')}
				>
					<ExternalLinkIcon />
				</button>
				<button
					type="button"
					onClick={copyUrl}
					title={wp.i18n.__('Copy URL', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Copy URL', 'media-cloud-sync')}
				>
					<CopyIcon />
				</button>
				<a
					href={src}
					download
					target="_blank"
					rel="noopener noreferrer"
					title={wp.i18n.__('Download', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Download', 'media-cloud-sync')}
				>
					<DownloadIcon />
				</a>
				{mediaLibraryUrl ? (
					<a
						href={mediaLibraryUrl}
						target="_blank"
						rel="noopener noreferrer"
						title={wp.i18n.__(
							'View in Media Library',
							'media-cloud-sync'
						)}
						aria-label={wp.i18n.__(
							'View in Media Library',
							'media-cloud-sync'
						)}
					>
						<MediaLibraryIcon />
					</a>
				) : null}
				<span className={style.divider} />
				<button
					type="button"
					onClick={onClose}
					title={wp.i18n.__('Close', 'media-cloud-sync')}
					aria-label={wp.i18n.__('Close', 'media-cloud-sync')}
				>
					<CloseIcon />
				</button>
			</div>

			<button
				type="button"
				className={style.close}
				onClick={onClose}
				aria-label={wp.i18n.__('Close', 'media-cloud-sync')}
			>
				<img src={closeIcon} alt="" />
			</button>

			<div
				className={style.imageWrap}
				onMouseDown={onMouseDown}
				onMouseMove={onMouseMove}
				onMouseUp={stopDragging}
				onMouseLeave={stopDragging}
			>
				<img
					className={style.image}
					src={src}
					alt={alt}
					draggable={false}
					style={{
						transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom}) rotate(${rotation}deg)`,
						cursor: zoom > 1 ? (dragging ? 'grabbing' : 'grab') : 'default'
					}}
				/>
			</div>
		</div>
	);
}
