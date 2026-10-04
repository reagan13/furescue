<?php

declare(strict_types=1);

/** @var list<string> $attachments */
/** @var list<string> $proofUrls */
/** @var string $statusRaw */
/** @var array<string, mixed> $caseRow */
/** @var string $rescuerName */
/** @var callable $badgeHtml */

$attachmentCount = count($attachments);
$canUploadAttachments = $statusRaw !== 'resolved' && !empty($caseRow['report_id']);
$filesGallery = $attachmentCount > 0
    ? '<div class="cd-files">' . implode('', array_map(
        static fn(string $f): string => '<a class="cd-file" href="' . e($f) . '" target="_blank" rel="noopener"><img src="' . e($f) . '" alt="Case attachment" loading="lazy"></a>',
        $attachments
    )) . '</div>'
    : '<div class="empty-state"><i data-lucide="image-off"></i><span>'
        . ($canUploadAttachments ? 'Upload at least one attached file. Required before resolve.' : 'No attachments submitted.')
        . '</span></div>';
$attachForm = $canUploadAttachments
    ? '<form class="cd-upload" data-cd-upload="attachments" enctype="multipart/form-data">
        <p class="cd-upload-hint">JPG, PNG, GIF, WEBP, MP4, or WEBM. Up to 10 MB each.</p>
        <div class="cd-upload-row">
          <input class="input" type="file" name="files[]" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm" multiple>
          ' . button_html('Upload', 'outline', 'sm', '', 'upload', '', 'submit') . '
        </div>
        <p class="cd-upload-names" hidden></p>
        <p class="cd-upload-error" hidden></p>
      </form>'
    : '';

$attachmentsPanelHtml = '
  <div class="panel case-detail-panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="paperclip"></i>
        <h2 class="panel-title">Attached files</h2>
      </div>
      ' . ($attachmentCount > 0 ? '<span class="stamp stamp--sm stamp--muted">' . e((string) $attachmentCount) . '</span>' : '') . '
    </div>
    <div class="panel-body">' . $filesGallery . $attachForm . '</div>
  </div>';

$proofCount = count($proofUrls);
$canUploadProof = $statusRaw === 'in_progress';
$proofGallery = $proofCount > 0
    ? '<div class="cd-files">' . implode('', array_map(
        static fn(string $p): string => '<a class="cd-file" href="' . e($p) . '" target="_blank" rel="noopener"><img src="' . e($p) . '" alt="Rescue proof" loading="lazy"></a>',
        $proofUrls
    )) . '</div>'
    : '<div class="empty-state"><i data-lucide="image-off"></i><span>'
        . ($canUploadProof ? 'Upload rescue proof. Required before resolve.' : 'No rescue proof uploaded.')
        . '</span></div>';
$proofMeta = $proofCount > 0
    ? '<div class="cd-rescuer-meta">' . $badgeHtml($rescuerName !== '' ? $rescuerName : 'Rescuer', 'secondary', 'user') . '</div>'
    : '';
$proofForm = $canUploadProof
    ? '<form class="cd-upload" data-cd-upload="proof" enctype="multipart/form-data">
        <p class="cd-upload-hint">JPG, PNG, or WEBP. Up to 5 MB each.</p>
        <div class="cd-upload-row">
          <input class="input" type="file" name="files[]" accept="image/jpeg,image/png,image/webp" multiple>
          ' . button_html('Upload', 'outline', 'sm', '', 'upload', '', 'submit') . '
        </div>
        <p class="cd-upload-names" hidden></p>
        <p class="cd-upload-error" hidden></p>
      </form>'
    : '';

$proofPanelHtml = '
  <div class="panel case-detail-panel">
    <div class="panel-head">
      <div class="panel-title-wrap">
        <i data-lucide="camera"></i>
        <h2 class="panel-title">Rescue proof</h2>
      </div>
      ' . $proofMeta . '
    </div>
    <div class="panel-body">
      ' . $proofGallery . $proofForm . '
    </div>
  </div>';
