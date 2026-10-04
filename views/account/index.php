<?php

declare(strict_types=1);

$esc = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fullNameRaw = (string) ($userData['full_name'] ?? ($residentUser['full_name'] ?? ''));
$fullName = $esc($fullNameRaw);
$phone = $esc($userData['phone_number'] ?? '');
$address = $esc($userData['address'] ?? '');
$email = $esc($userData['email'] ?? ($residentUser['email'] ?? ''));
$photoUrl = trim((string) ($userData['profile_photo_url'] ?? ''));
$hasPhoto = $photoUrl !== '';
$words = preg_split('/\s+/u', trim($fullNameRaw)) ?: [];
$words = array_values(array_filter($words, static fn($w) => $w !== ''));
$initials = '';
foreach (array_slice($words, 0, 2) as $word) {
    $initials .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
}
if ($initials === '') {
    $initials = '?';
}
?>
    <div class="account-page mx-auto w-full max-w-4xl min-w-0">
      <div class="rpage-head">
        <div class="min-w-0">
          <h1 class="rpage-title">Account</h1>
          <p class="rpage-sub">Update the photo, name, phone, and address on your FurEscue profile.</p>
        </div>
      </div>

      <section class="rcard account-photo-card" aria-labelledby="account-photo-title">
        <div class="account-photo">
          <div class="account-photo-preview" data-account-photo-preview>
            <img id="account-photo-img" src="<?= $hasPhoto ? $esc($photoUrl) : '' ?>" alt="Your profile photo"<?= $hasPhoto ? '' : ' hidden' ?>>
            <span id="account-photo-fallback" class="account-photo-fallback"<?= $hasPhoto ? ' hidden' : '' ?>><?= $esc($initials) ?></span>
          </div>
          <div class="account-photo-meta">
            <h2 id="account-photo-title" class="account-photo-title">Profile photo</h2>
            <p class="account-photo-hint">JPG, PNG, or WEBP. Up to 5 MB.</p>
            <div class="account-photo-actions">
              <button type="button" class="rbtn rbtn--ghost rbtn--sm" id="account-photo-change">
                <i data-lucide="camera"></i><span>Change photo</span>
              </button>
              <button type="button" class="rbtn rbtn--danger-ghost rbtn--sm" id="account-photo-remove"<?= $hasPhoto ? '' : ' hidden' ?>>
                <i data-lucide="trash-2"></i><span>Remove</span>
              </button>
            </div>
            <input id="account-photo-input" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" hidden>
          </div>
        </div>
      </section>

      <form id="account-form" class="rcard" novalidate>
        <div class="rmodal-body">
          <div class="rform-field">
            <label for="full_name" class="rform-label">Full name</label>
            <input id="full_name" name="full_name" type="text" class="input" maxlength="150" required autocomplete="name" value="<?= $fullName ?>">
          </div>
          <div class="rform-field">
            <label for="account-email" class="rform-label">Email</label>
            <input id="account-email" type="email" class="input" value="<?= $email ?>" autocomplete="email" disabled>
          </div>
          <div class="rform-field">
            <label for="phone_number" class="rform-label">Phone number</label>
            <input id="phone_number" name="phone_number" type="tel" class="input" maxlength="20" autocomplete="tel" value="<?= $phone ?>">
          </div>
          <div class="rform-field">
            <label for="address" class="rform-label">Address</label>
            <textarea id="address" name="address" class="input input--area" rows="3" maxlength="2000" autocomplete="street-address"><?= $address ?></textarea>
          </div>
          <p class="rform-error" id="account-error" hidden><i data-lucide="alert-circle"></i><span></span></p>
        </div>
        <div class="rmodal-foot">
          <button type="submit" class="rbtn rbtn--solid" id="account-save">
            <i data-lucide="save"></i><span>Save changes</span>
          </button>
        </div>
      </form>
    </div>
