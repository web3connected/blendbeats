export const asset = (path) => {
    const PUBLIC_URL = window.location.origin;

    // If path is null or undefined, return empty string
    if (!path) {
        return '';
    }

    window.console.log('PUBLIC_URL', PUBLIC_URL, path);
    // If path is already an absolute URL, return it
    if (path.startsWith('http')) {
        return path;
    }

    // Return the full URL with public path
    return `${PUBLIC_URL}/${path}`;
};


export const getFileType = (filename) => {
    if (!filename) return { type: 'unknown', icon: 'fa fa-file  ' };

    const extension = filename.split('.').pop().toLowerCase();
    const fileTypes = {
        'jpg': { type: 'image', icon: 'fa fa-file-image' },
        'jpeg': { type: 'image', icon: 'fa fa-file-image' },
        'png': { type: 'image', icon: 'fa fa-file-image' },
        'gif': { type: 'image', icon: 'fa fa-file-image' },
        'svg': { type: 'image', icon: 'fa fa-file-image' },
        'bmp': { type: 'image', icon: 'fa fa-file-image' },
        'webp': { type: 'image', icon: 'fa fa-file-image' },
        'ico': { type: 'image', icon: 'fa fa-file-image' },
        'tiff': { type: 'image', icon: 'fa fa-file-image' },
        'mp4': { type: 'video', icon: 'fa fa-file-video' },
        'avi': { type: 'video', icon: 'fa fa-file-video' },
        'mkv': { type: 'video', icon: 'fa fa-file-video' },
        'flv': { type: 'video', icon: 'fa fa-file-video' },
        'wmv': { type: 'video', icon: 'fa fa-file-video' },
        'webm': { type: 'video', icon: 'fa fa-file-video' },
        'mp3': { type: 'audio', icon: 'fa fa-file-audio' },
        'ogg': { type: 'audio', icon: 'fa fa-file-audio' },
        'wav': { type: 'audio', icon: 'fa fa-file-audio' },
        'aac': { type: 'audio', icon: 'fa fa-file-audio' },
        'pdf': { type: 'document', icon: 'fa fa-file-pdf' },
        'doc': { type: 'document', icon: 'fa fa-file-word' },
        'docx': { type: 'document', icon: 'fa fa-file-word' },
        'xls': { type: 'document', icon: 'fa fa-file-excel' },
        'xlsx': { type: 'document', icon: 'fa fa-file-excel' },
        'ppt': { type: 'document', icon: 'fa fa-file-powerpoint' },
        'pptx': { type: 'document', icon: 'fa fa-file-powerpoint' },
        'txt': { type: 'document', icon: 'fa fa-file-text' },
        'json': { type: 'document', icon: 'fa fa-file-code' },
        'xml': { type: 'document', icon: 'fa fa-file-code' },
        'csv': { type: 'document', icon: 'fa fa-file-csv' },
        'zip': { type: 'archive', icon: 'fa fa-file-archive' },
        'rar': { type: 'archive', icon: 'fa fa-file-archive' },
    }; return fileTypes[extension] || { type: 'unknown', icon: 'unknown-icon' };
};
export const getPathName = (currentPath = "") => {
    if (!currentPath) return "";

    // Normalize and remove host name if present (supports both http & https)
    const sanitizedPath = currentPath.replace(/^https?:\/\/[^/]+/, "").replace(/\/$/, "");

    // Split the path into parts
    const pathSegments = sanitizedPath.split('/').filter(Boolean);

    // Return the last two segments if available, otherwise return the first
    return pathSegments.length > 1 ? pathSegments.slice(-2).join('/') : pathSegments[0] || "";
};


export const getRoot = () => {
    return 'media';
}
