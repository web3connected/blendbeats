<style>
    .admin-avatar-preview {
        align-items: center;
        background: #111827;
        border: 1px solid #374151;
        border-radius: 999px;
        display: flex;
        height: 192px;
        justify-content: center;
        overflow: hidden;
        width: 192px;
    }

    .admin-avatar-preview img {
        display: none;
        height: 100%;
        object-fit: cover;
        width: 100%;
    }

    .admin-avatar-initials {
        align-items: center;
        background: #dc3545;
        color: #fff;
        display: none;
        font-size: 56px;
        font-weight: 700;
        height: 100%;
        justify-content: center;
        width: 100%;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const panel = document.querySelector('[data-avatar-panel]');

        if (!panel) {
            return;
        }

        const fileInput = panel.querySelector('[data-avatar-file]');
        const gravatarToggle = panel.querySelector('[data-gravatar-toggle]');
        const image = panel.querySelector('[data-avatar-image]');
        const initials = panel.querySelector('[data-avatar-initials]');
        const sourceLabels = panel.querySelectorAll('[data-avatar-source], [data-avatar-source-inline]');
        const fallbackSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="192" height="192" viewBox="0 0 192 192"><rect width="192" height="192" rx="96" fill="#374151"/><path fill="#9ca3af" d="M96 96c17.7 0 32-14.3 32-32S113.7 32 96 32 64 46.3 64 64s14.3 32 32 32Zm0 16c-27.6 0-52 14.1-66.3 35.5A88 88 0 0 0 96 184a88 88 0 0 0 66.3-36.5C148 126.1 123.6 112 96 112Z"/></svg>`;
        const fallbackUrl = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(fallbackSvg)}`;

        let selectedFileUrl = null;

        const setSourceLabel = (label) => {
            sourceLabels.forEach((sourceLabel) => {
                sourceLabel.textContent = label;
            });
        };

        const showImage = (src, label) => {
            image.src = src;
            image.style.display = 'block';
            initials.style.display = 'none';
            setSourceLabel(label);
        };

        const showInitials = () => {
            initials.textContent = panel.dataset.initials || '';
            image.removeAttribute('src');
            image.style.display = 'none';
            initials.style.display = 'flex';
            setSourceLabel('Generated Initial Avatar');
        };

        const updatePreview = () => {
            if (selectedFileUrl) {
                showImage(selectedFileUrl, 'Uploaded Avatar');
                return;
            }

            if (panel.dataset.uploadedAvatar) {
                showImage(panel.dataset.uploadedAvatar, 'Uploaded Avatar');
                return;
            }

            if (gravatarToggle.checked) {
                showImage(panel.dataset.gravatar, 'Gravatar');
                return;
            }

            if (panel.dataset.initials) {
                showInitials();
                return;
            }

            showImage(fallbackUrl, 'Default Fallback Image');
        };

        fileInput.addEventListener('change', () => {
            if (selectedFileUrl) {
                URL.revokeObjectURL(selectedFileUrl);
                selectedFileUrl = null;
            }

            if (fileInput.files.length > 0) {
                selectedFileUrl = URL.createObjectURL(fileInput.files[0]);
                gravatarToggle.checked = false;
            }

            updatePreview();
        });

        image.addEventListener('error', () => {
            if (panel.dataset.initials) {
                showInitials();
                return;
            }

            showImage(fallbackUrl, 'Default Fallback Image');
        });

        gravatarToggle.addEventListener('change', updatePreview);
        updatePreview();
    });
</script>
