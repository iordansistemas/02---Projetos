/**
 * View: Dashboard Principal & Feed de Presença em Tempo Real
 */

const DashboardView = {
  pollingTimer: null,

  async render() {
    this.stopPolling();
    await this.loadData();
    // Inicia polling em tempo real a cada 5 segundos
    this.pollingTimer = setInterval(() => this.loadData(true), 5000);
  },

  stopPolling() {
    if (this.pollingTimer) {
      clearInterval(this.pollingTimer);
      this.pollingTimer = null;
    }
  },

  async loadData(isSilent = false) {
    try {
      const res = await API.getPresenceFeed(50);
      if (!res.success) return;

      // Atualiza métricas
      document.getElementById('metric-total').textContent = res.stats.total;
      document.getElementById('metric-ciencia').textContent = res.stats.ciencia;
      document.getElementById('metric-presentes').textContent = res.stats.presentes;
      document.getElementById('metric-ausentes').textContent = res.stats.ausentes;

      // Atualiza horário de sincronização
      const syncEl = document.getElementById('last-sync-time');
      if (syncEl) syncEl.textContent = new Date().toLocaleTimeString('pt-BR');

      // Renderiza grid de cartões de presença com fotos
      const feedContainer = document.getElementById('presence-feed-grid');
      if (!feedContainer) return;

      if (res.data.length === 0) {
        feedContainer.innerHTML = `
          <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="bi bi-clock-history" style="font-size: 2.5rem; color: var(--text-dim);">
            <p style="margin-top: 10px;">Ainda nenhum agraciado registrou presença na formatura hoje.</p>
            <p style="font-size: 0.8rem;">Os registros da recepção aparecerão aqui instantaneamente em tempo real com foto.</p>
          </div>
        `;
        return;
      }

      feedContainer.innerHTML = res.data.map(item => {
        const fotoHtml = item.foto_url
          ? `<img src="${item.foto_url}" class="presence-avatar" alt="${item.nome_completo}" onerror="this.src='https://img.icons8.com/color/96/user.png'">`
          : `<div class="presence-avatar-placeholder"><i class="bi bi-person-badge"></i></div>`;

        const hora = item.data_checkin ? new Date(item.data_checkin).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '--:--';

        return `
          <div class="presence-card">
            ${fotoHtml}
            <div class="presence-details">
              <h4>${item.posto_graduacao} ${item.nome_completo}</h4>
              <p><i class="bi bi-award-fill"></i> ${item.medalha}</p>
              <div class="meta">
                <span>RE: ${item.re || 'N/A'}</span> • <span>${item.unidade}</span>
              </div>
              <div class="presence-time">
                <i class="bi bi-check-circle-fill"></i> Presente às ${hora}
              </div>
            </div>
          </div>
        `;
      }).join('');

    } catch (err) {
      if (!isSilent) console.error("Erro ao carregar dashboard:", err);
    }
  }
};
