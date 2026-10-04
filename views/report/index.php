<?php

declare(strict_types=1);

$fieldCls = 'input';
$labelCls = 'mb-1.5 block text-sm font-bold';
$errorCls = 'mt-1 hidden items-center gap-1 text-xs font-semibold text-destructive';
$cardCls = 'rounded-xl border bg-card text-card-foreground shadow-sm';
$sectionTitle = static fn(string $icon, string $text): string =>
    '<div class="flex items-center gap-2"><i data-lucide="' . $icon . '" class="h-4 w-4 text-primary"></i>'
    . '<h2 class="text-xs font-extrabold uppercase tracking-wider text-muted-foreground">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</h2></div>';
$btnPrimary = 'inline-flex h-10 w-full items-center justify-center gap-2 whitespace-nowrap rounded-md bg-primary px-4 text-sm font-bold text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50';
$btnOutline = 'inline-flex h-8 items-center justify-center gap-2 whitespace-nowrap rounded-md border border-input bg-background px-3 text-[13px] font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50';
?>
  <div class="mx-auto w-full max-w-4xl">
    <div class="mb-6">
      <span class="inline-flex items-center rounded-full bg-secondary px-2.5 py-0.5 text-[11px] font-extrabold uppercase tracking-wider text-secondary-foreground">Community</span>
      <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Report a stray animal</h1>
      <p class="mt-1 text-sm text-muted-foreground">Pin the location on the map, describe the animal, and attach photos. Our team verifies every report.</p>
    </div>

    <form id="report-form" method="post" action="#" class="grid grid-cols-1 gap-5 lg:grid-cols-2" novalidate>
      <div class="flex flex-col gap-5">
        <div class="<?= $cardCls ?> p-4 sm:p-5">
          <?= $sectionTitle('file-text', 'Animal details') ?>
          <div class="mt-3">
            <label for="animal_description" class="<?= $labelCls ?>">Description <span class="font-normal text-muted-foreground">(required)</span></label>
            <textarea id="animal_description" name="animal_description" rows="6" maxlength="2000" required class="<?= $fieldCls ?> input--area input--area-lg" placeholder="What does the animal look like? Condition, behavior, anything that helps the rescue team."></textarea>
            <div class="mt-1 flex items-center justify-between">
              <p data-error-for="animal_description" class="<?= $errorCls ?>"><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span>Please describe the animal.</span></p>
              <span id="desc-count" class="ml-auto text-xs tabular-nums text-muted-foreground">0 / 2000</span>
            </div>
          </div>
        </div>

        <div class="<?= $cardCls ?> p-4 sm:p-5">
          <?= $sectionTitle('camera', 'Photos &amp; videos') ?>
          <p class="mt-2 text-xs text-muted-foreground">Required — attach at least one clear photo or video. Up to 8 files (images or videos).</p>
          <label for="report-photos" id="photo-drop" class="mt-3 flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-input bg-background px-4 py-6 text-center transition-colors hover:bg-accent/50">
            <i data-lucide="image-plus" class="h-6 w-6 text-muted-foreground"></i>
            <span class="text-sm font-semibold">Choose files <span class="font-normal text-muted-foreground">(required)</span></span>
            <span class="text-xs text-muted-foreground">JPG, PNG, GIF, WEBP, MP4 or WEBM — max 10 MB each</span>
            <input id="report-photos" name="photos[]" type="file" multiple accept="image/*,video/*" required aria-required="true" class="sr-only">
          </label>
          <p data-error-for="photos" class="<?= $errorCls ?>"><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span>Please attach at least one photo or video.</span></p>
          <ul id="photo-list" class="mt-2 flex list-none flex-col gap-1.5 p-0"></ul>
        </div>
      </div>

      <div class="flex flex-col gap-5">
        <div class="<?= $cardCls ?> overflow-hidden">
          <div class="p-4 pb-3 sm:px-5">
            <?= $sectionTitle('map-pin', 'Location') ?>
            <p class="mt-2 text-xs text-muted-foreground">Tap the map to drop a pin inside Mati City.</p>
          </div>
          <div id="report-map" class="h-64 w-full sm:h-80 lg:h-72 xl:h-80" aria-label="Location picker map"></div>
          <p id="report-map-status" class="hidden px-4 pt-2 text-xs font-semibold text-destructive" role="status"></p>
          <div class="space-y-3 p-4 sm:p-5">
            <div data-error-for="location" class="<?= $errorCls ?>"><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i><span>Drop a pin on the map first.</span></div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="latitude" class="<?= $labelCls ?>">Latitude</label>
                <input id="latitude" name="latitude" type="text" inputmode="decimal" readonly placeholder="—" value="" class="<?= $fieldCls ?> cursor-default font-mono text-xs">
              </div>
              <div>
                <label for="longitude" class="<?= $labelCls ?>">Longitude</label>
                <input id="longitude" name="longitude" type="text" inputmode="decimal" readonly placeholder="—" value="" class="<?= $fieldCls ?> cursor-default font-mono text-xs">
              </div>
            </div>
            <button type="button" id="use-my-location" class="<?= $btnOutline ?> w-full">
              <i data-lucide="locate-fixed" class="h-4 w-4"></i><span>Use my current location</span>
            </button>
            <div>
              <label for="address_text" class="<?= $labelCls ?>">Address <span class="font-normal text-muted-foreground">(optional)</span></label>
              <input id="address_text" name="address_text" type="text" maxlength="500" placeholder="Barangay, street, landmark…" value="" class="<?= $fieldCls ?>">
              <p class="mt-1 text-xs text-muted-foreground">Filled automatically from the pin — edit it if needed.</p>
            </div>
          </div>
        </div>

        <button type="submit" id="report-submit" class="<?= $btnPrimary ?>">
          <i data-lucide="send" class="h-4 w-4"></i><span>Submit report</span>
        </button>
      </div>
    </form>
  </div>
