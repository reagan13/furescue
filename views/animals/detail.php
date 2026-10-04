    <div class="mx-auto w-full max-w-6xl">
      <p class="rdetail-back"><a href="<?= htmlspecialchars($backHref ?? '/animals/', ENT_QUOTES, 'UTF-8') ?>" class="rbtn rbtn--ghost rbtn--sm"><i data-lucide="arrow-left"></i><span><?= htmlspecialchars($backLabel ?? 'Back to gallery', ENT_QUOTES, 'UTF-8') ?></span></a></p>
      <div id="detail-root" aria-live="polite">
        <div class="rempty">
          <i data-lucide="loader-circle"></i>
          <p class="rempty-text">Loading profile…</p>
        </div>
      </div>
    </div>
