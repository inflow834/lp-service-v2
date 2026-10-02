export const TABLE_ICONS = {
	double_circle: {
		label: '二重丸',
		svg: (
			<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
				<circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" strokeWidth="4" />
				<circle cx="24" cy="24" r="11" fill="none" stroke="currentColor" strokeWidth="4" />
			</svg>
		),
	},
	circle: {
		label: '一重丸',
		svg: (
			<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
				<circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" strokeWidth="4" />
			</svg>
		),
	},
	cross: {
		label: 'バツ',
		svg: (
			<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
				<line x1="10" y1="10" x2="38" y2="38" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
				<line x1="38" y1="10" x2="10" y2="38" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
			</svg>
		),
	},
};
