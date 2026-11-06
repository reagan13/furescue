<div class="rpage-head">
  <div>
    <h2 class="rpage-title">Notifications</h2>
    <p class="rpage-sub">Updates about your reports, adoptions, messages, and announcements. This inbox refreshes when you open it. The built-in PHP server cannot hold a live stream, so a new alert is not a push.</p>
  </div>
  <div class="rpage-actions">
    <button type="button" class="rbtn rbtn--ghost rbtn--sm" id="notif-mark-all">
      <i data-lucide="check-check"></i><span>Mark all as read</span>
    </button>
  </div>
</div>

<div class="rcard notif-card">
  <div class="rcard-head notif-tabs-wrap">
    <div class="notif-tabs" role="tablist" aria-label="Filter notifications">
      <button type="button" role="tab" class="notif-tab is-active" data-filter="all" aria-selected="true">All</button>
      <button type="button" role="tab" class="notif-tab" data-filter="unread" aria-selected="false">
        Unread <span class="rchip rchip--alert notif-unread-chip" id="notif-unread-count" hidden>0</span>
      </button>
    </div>
  </div>

  <ul class="notif-list" id="notif-list" aria-live="polite">
    <li class="rempty"><p class="rempty-text">Loading notifications&hellip;</p></li>
  </ul>
</div>
