const avatarInput = document.getElementById('avatarInput');
const avatarPreview = document.getElementById('avatarPreview');
const removeAvatarFlag = document.getElementById('removeAvatarFlag');
const uploadConfig = document.getElementById('avatarUploadConfig');

if (avatarInput && avatarPreview && removeAvatarFlag && uploadConfig) {
    const projectUrl = uploadConfig.dataset.projectUrl;
    const anonKey = uploadConfig.dataset.anonKey;
    const bucket = uploadConfig.dataset.bucket;
    const uploadUrl = uploadConfig.dataset.uploadUrl;
    const completeUrl = uploadConfig.dataset.completeUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const allowedTypes = new Set(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

    const notifyError = (message) => {
        if (typeof window.Swal === 'undefined') {
            window.alert(message);
            return;
        }

        window.Swal.fire({
            icon: 'error',
            title: 'Không thể cập nhật ảnh',
            text: message,
            confirmButtonText: 'Đã hiểu',
        });
    };

    avatarInput.addEventListener('change', async () => {
        const file = avatarInput.files?.[0];
        if (!file) {
            return;
        }

        if (!allowedTypes.has(file.type)) {
            notifyError('Vui lòng chọn ảnh JPEG, PNG, GIF hoặc WebP.');
            avatarInput.value = '';
            return;
        }

        if (file.size === 0 || file.size > 2 * 1024 * 1024) {
            notifyError('Kích thước file phải lớn hơn 0 và không vượt quá 2MB.');
            avatarInput.value = '';
            return;
        }

        if (!projectUrl || !anonKey || !bucket || !csrfToken) {
            notifyError('Thiếu cấu hình kết nối Supabase. Vui lòng liên hệ quản trị viên.');
            avatarInput.value = '';
            return;
        }

        const previousPreviewUrl = avatarPreview.src;
        const previewObjectUrl = URL.createObjectURL(file);
        avatarPreview.src = previewObjectUrl;
        removeAvatarFlag.value = '0';

        try {
            const signingResponse = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    content_type: file.type,
                    file_size: file.size,
                }),
            });
            const signingResult = await signingResponse.json();

            if (!signingResponse.ok || !signingResult.success) {
                throw new Error(signingResult.message || 'Không thể chuẩn bị tải ảnh lên.');
            }

            const { createClient } = await import('@supabase/supabase-js');
            const supabase = createClient(projectUrl, anonKey);
            const { error: uploadError } = await supabase.storage
                .from(bucket)
                .uploadToSignedUrl(signingResult.path, signingResult.token, file, {
                    contentType: file.type,
                    cacheControl: '0',
                    upsert: true,
                });

            if (uploadError) {
                throw uploadError;
            }

            const completeResponse = await fetch(completeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    path: signingResult.path,
                    content_type: file.type,
                }),
            });
            const completeResult = await completeResponse.json();

            if (!completeResponse.ok || !completeResult.success) {
                throw new Error(completeResult.message || 'Không thể lưu URL ảnh đại diện.');
            }

            document.querySelectorAll('[data-user-avatar]').forEach((image) => {
                image.src = completeResult.avatar_url;
            });
        } catch (error) {
            avatarPreview.src = previousPreviewUrl;
            notifyError(error instanceof Error ? error.message : 'Có lỗi xảy ra khi tải ảnh lên.');
        } finally {
            URL.revokeObjectURL(previewObjectUrl);
            avatarInput.value = '';
        }
    });
}
