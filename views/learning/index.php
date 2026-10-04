<div class="rpage-head">
  <div>
    <h2 class="rpage-title">Responsible Pet Ownership, One Lesson at a Time</h2>
    <p class="rpage-sub">Read each lesson, take the quiz at the end, and earn a badge. The bar fills as you read.</p>
  </div>
</div>

<section class="learn-progress rcard" aria-label="Your progress">
  <div class="rcard-head">
    <h3 class="rcard-title"><i data-lucide="graduation-cap"></i> Your progress</h3>
    <span class="rchip" id="learn-progress-chip">Loading&hellip;</span>
  </div>
  <div class="rcard-pad learn-progress-body">
    <div class="learn-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="learn-progress-bar">
      <span class="learn-progress-fill" style="width:0%"></span>
    </div>
    <p class="learn-progress-note" id="learn-progress-note">Fetching your modules&hellip;</p>
  </div>
</section>

<section id="learn-list-section" aria-label="Modules">
  <div class="learn-grid" id="learn-grid"></div>
</section>

<section id="learn-lesson-section" class="is-hidden" aria-label="Lesson">
  <button type="button" class="rbtn rbtn--ghost rbtn--sm learn-back" id="learn-back">
    <i data-lucide="arrow-left"></i><span>All modules</span>
  </button>
  <article class="rcard learn-lesson">
    <header class="rcard-head learn-lesson-head">
      <div>
        <span class="rchip rchip--brand" id="learn-lesson-category">Module</span>
        <h3 class="learn-lesson-title" id="learn-lesson-title">&nbsp;</h3>
      </div>
      <span class="rchip" id="learn-lesson-status"></span>
    </header>
    <div class="rcard-pad">
      <div class="learn-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="learn-lesson-progress">
        <span class="learn-progress-fill" style="width:0%"></span>
      </div>
    </div>
    <div class="rcard-pad learn-lesson-body prose-module" id="learn-lesson-content"></div>
    <div class="rcard-pad" id="learn-badge" hidden></div>
    <div class="rcard-pad learn-quiz" id="learn-quiz" hidden></div>
    <footer class="learn-lesson-foot">
      <p class="learn-lesson-hint" id="learn-lesson-hint">Read to the end, then take the quiz. Passing it completes the lesson.</p>
    </footer>
  </article>
</section>
