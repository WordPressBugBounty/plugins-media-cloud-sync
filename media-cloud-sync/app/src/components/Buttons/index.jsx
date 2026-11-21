import React from 'react';
import { Link } from 'react-router-dom';
import style from './style.scss';
import loader from '../../images/loader-blue.svg';
import { ClassNames } from '../../helper';

export function PrimaryButton({
	link = '#',
	label = wp.i18n.__('Button', 'media-cloud-sync'),
	type = 'link',
	onClick = '',
	disabled = false,
	loading = false,
	arrow = false,
	arrowDirection = 'right',
	size = 'default',
	variant = 'default',
	className = '',
	...props
}) {
	const sizeClass =
		variant === 'default' && size === 'small' ? style.primarySmall : null;
	const variantClasses = {
		corner: style.primaryCorner,
		'corner-warning': style.primaryCornerWarning
	};
	const variantClass = variantClasses[variant] || null;

	return type === 'link' ? (
		<Link
			to={link}
			className={ClassNames([
				style.primary,
				sizeClass,
				variantClass,
				className
			])}
			{...props}
		>
			{label}{' '}
			{arrow ? (
				<svg
					width="14"
					height="11"
					viewBox="0 0 14 11"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					className={style?.[arrowDirection]}
				>
					<path
						d="M8.26244 9.90286C8.09551 9.73592 8.01538 9.53421 8.02205 9.29772C8.02929 9.06123 8.11637 8.85952 8.28331 8.69259L10.6413 6.33464H1.33467C1.09818 6.33464 0.899808 6.25451 0.739551 6.09426C0.57985 5.93456 0.5 5.73646 0.5 5.49997C0.5 5.26348 0.57985 5.06511 0.739551 4.90485C0.899808 4.74515 1.09818 4.6653 1.33467 4.6653H10.6413L8.26244 2.28649C8.09551 2.11955 8.01204 1.92118 8.01204 1.69137C8.01204 1.46211 8.09551 1.26401 8.26244 1.09708C8.42937 0.930147 8.62775 0.84668 8.85756 0.84668C9.08682 0.84668 9.28491 0.930147 9.45185 1.09708L13.2705 4.9157C13.3539 4.99917 13.4132 5.08959 13.4482 5.18697C13.4827 5.28435 13.5 5.38868 13.5 5.49997C13.5 5.61126 13.4827 5.71559 13.4482 5.81297C13.4132 5.91035 13.3539 6.00077 13.2705 6.08424L9.43098 9.92373C9.27796 10.0767 9.08682 10.1533 8.85756 10.1533C8.62775 10.1533 8.42937 10.0698 8.26244 9.90286V9.90286Z"
						fill="white"
					/>
				</svg>
			) : null}
		</Link>
	) : (
		<div className={style.primaryOuter}>
			<button
				type="button"
				onClick={onClick}
				className={ClassNames([
					style.primary,
					sizeClass,
					variantClass,
					loading ? style.loading : null,
					className
				])}
				{...props}
				disabled={disabled || loading}
			>
				{label}
			</button>
			{loading ? <img src={loader} alt="" /> : null}
		</div>
	);
}

export function SecondaryButton({
	link = '#',
	label = wp.i18n.__('Button', 'media-cloud-sync'),
	type = 'link',
	onClick = '',
	disabled = false,
	loading = false,
	arrow = false,
	arrowDirection = 'right',
	className = '',
	variant = '',
	...props
}) {
	const variantClass = variant === 'danger' ? style.danger : null;
	return type === 'link' ? (
		<Link
			to={link}
			className={ClassNames([style.secondary, variantClass, className])}
			{...props}
		>
			{label}{' '}
			{arrow ? (
				<svg
					width="14"
					height="11"
					viewBox="0 0 14 11"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					className={style?.[arrowDirection]}
				>
					<path
						d="M8.26244 9.90286C8.09551 9.73592 8.01538 9.53421 8.02205 9.29772C8.02929 9.06123 8.11637 8.85952 8.28331 8.69259L10.6413 6.33464H1.33467C1.09818 6.33464 0.899808 6.25451 0.739551 6.09426C0.57985 5.93456 0.5 5.73646 0.5 5.49997C0.5 5.26348 0.57985 5.06511 0.739551 4.90485C0.899808 4.74515 1.09818 4.6653 1.33467 4.6653H10.6413L8.26244 2.28649C8.09551 2.11955 8.01204 1.92118 8.01204 1.69137C8.01204 1.46211 8.09551 1.26401 8.26244 1.09708C8.42937 0.930147 8.62775 0.84668 8.85756 0.84668C9.08682 0.84668 9.28491 0.930147 9.45185 1.09708L13.2705 4.9157C13.3539 4.99917 13.4132 5.08959 13.4482 5.18697C13.4827 5.28435 13.5 5.38868 13.5 5.49997C13.5 5.61126 13.4827 5.71559 13.4482 5.81297C13.4132 5.91035 13.3539 6.00077 13.2705 6.08424L9.43098 9.92373C9.27796 10.0767 9.08682 10.1533 8.85756 10.1533C8.62775 10.1533 8.42937 10.0698 8.26244 9.90286V9.90286Z"
						fill="rgba(0, 0, 0, 0.6)"
					/>
				</svg>
			) : null}
		</Link>
	) : (
		<div className={style.primaryOuter}>
			<button
				type="button"
				onClick={onClick}
				className={ClassNames([
					style.secondary,
					variantClass,
					loading ? style.loading : null,
					className
				])}
				{...props}
				disabled={disabled || loading}
			>
				{label}
			</button>
			{loading ? <img src={loader} alt="" /> : null}
		</div>
	);
}

export function PremiumButton({
	link = '#',
	label = wp.i18n.__('PURCHASE PREMIUM', 'media-cloud-sync'),
	...props
}) {
	return (
		<a href={link} className={style.premium} {...props}>
			{label}
		</a>
	);
}
