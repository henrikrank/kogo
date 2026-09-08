export const getGalleryLayout = (total, columns, maxRows = 4) => {
	if (total <= 0 || columns <= 0) {
		return { cells: 0, moreIndex: -1, moreCount: 0 };
	}

	if (total <= columns) {
		return { cells: total, moreIndex: -1, moreCount: 0 };
	}

	const cells = Math.min(total, Math.floor(total / columns) * columns, columns * maxRows);
	return cells === total
		? { cells, moreIndex: -1, moreCount: 0 }
		: { cells, moreIndex: cells - 1, moreCount: total - cells + 1 };
};
