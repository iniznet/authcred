(function () {
	const MODAL_ID = 'authcred-managed-hosting-modal';
	let lastFocusedElement = null;

	function getModal() {
		return document.getElementById(MODAL_ID);
	}

	function openModal(event) {
		if (event) {
			event.preventDefault();
		}

		const modal = getModal();
		if (!modal) {
			return;
		}

		lastFocusedElement = document.activeElement;
		modal.hidden = false;
		document.body.classList.add('authcred-managed-hosting-modal-open');

		const dialog = modal.querySelector('.authcred-managed-hosting-modal__dialog');
		if (dialog) {
			dialog.focus();
		}
	}

	function closeModal() {
		const modal = getModal();
		if (!modal) {
			return;
		}

		modal.hidden = true;
		document.body.classList.remove('authcred-managed-hosting-modal-open');

		if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
			lastFocusedElement.focus();
		}
	}

	function onDocumentClick(event) {
		const openTrigger = event.target.closest('[data-authcred-managed-hosting-open]');
		if (openTrigger) {
			openModal(event);
			return;
		}

		const closeTrigger = event.target.closest('[data-authcred-managed-hosting-close]');
		if (closeTrigger) {
			event.preventDefault();
			closeModal();
		}
	}

	function onDocumentKeydown(event) {
		if (event.key !== 'Escape') {
			return;
		}

		const modal = getModal();
		if (!modal || modal.hidden) {
			return;
		}

		closeModal();
	}

	document.addEventListener('click', onDocumentClick);
	document.addEventListener('keydown', onDocumentKeydown);
}());
