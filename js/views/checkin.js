/**
 * View: Painel de Recepção & Check-in no Celular (Dia da Formatura)
 */

const CheckinView = {
  currentList: [],

  async render() {
    this.bindEvents();
    await this.loadList();
  },

  bindEvents() {
    const searchInput = document.getElementById('search-checkin');
    if (searchInput) {
      searchInput.oninput = () => this.filterList();
    }
  },

  async loadList() {
    try {
      const res = await API.getAllPresenceList();
      if (res.success) {
        this.currentList = res.data;
        this.renderCards(this.currentList);
      }
    } catch (err) {
      console.error("Erro ao carregar lista de check-in:", err);
    }
  },

  filterList() {
    const term = document.getElementById('search-checkin').value.toLowerCase().trim();
    if (!term) {
      this.renderCards(this.currentList);
      return;
    }

    const filtered = this.currentList.filter(item =>
      (item.nome_completo && item.nome_completo.toLowerCase().includes(term)) ||
      (item.re && item.re.toLowerCase().includes(term)) ||
      (item.cpf && item.cpf.toLowerCase().includes(term)) ||
      (item.unidade && item.unidade.toLowerCase().includes(term)) ||
      (item.medalha && item.medalha.toLowerCase().includes(term))
    );

    this.renderCards(filtered);
  },

  renderCards(list) {
    const container = document.getElementById('checkin-cards-container');
    if (!container) return;

    if (list.length === 0) {
      container.innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
          Nenhum agraciado encontrado para a busca realizada.
        </div>
      `;
      return;
    }

    container.innerHTML = list.map(item => {
      const isPresent = item.presente_formatura == 1;
      const fotoHtml = item.foto_url
        ? `<img src="${item.foto_url}" class="presence-avatar" alt="${item.nome_completo}" onerror="this.src='https://img.icons8.com/color/96/user.png'">`
        : `<div class="presence-avatar-placeholder"><i class="bi bi-person"></i></div>`;

      const btnClass = isPresent ? 'btn-danger' : 'btn-success';
      const btnText = isPresent ? 'Desmarcar' : 'Confirmar Presença';
      const btnIcon = isPresent ? 'bi-x-lg' : 'bi-check-lg';

      return `
        <div class="presence-card" style="border-left-color: ${isPresent ? 'var(--status-success)' : 'var(--text-dim)'};">
          ${fotoHtml}
          <div class="presence-details" style="flex: 1;">
            <h4>${item.posto_graduacao} ${item.nome_completo}</h4>
            <p><i class="bi bi-award-fill"></i> ${item.medalha}</p>
            <div class="meta">
              <span>RE: ${item.re || 'N/A'}</span> • <span>${item.unidade}</span>
            </div>
            ${item.mesa_setor ? `<div class="meta" style="color: var(--accent-gold); font-weight: 600;"><i class="bi bi-geo-alt"></i> Local: ${item.mesa_setor}</div>` : ''}
            ${item.data_checkin ? `<div class="presence-time"><i class="bi bi-clock-history"></i> Chegou às ${new Date(item.data_checkin).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</div>` : ''}
          </div>
          <div>
            ${Auth.isOrganizador() ? `
              <button class="btn ${btnClass} btn-sm btn-lg-touch" onclick="CheckinView.toggle(${item.id}, ${isPresent ? 0 : 1})">
                <i class="bi ${btnIcon}"></i> ${btnText}
              </button>
            ` : `<span class="badge ${isPresent ? 'badge-success' : 'badge-danger'}"><i class="bi ${isPresent ? 'bi-check-circle-fill' : 'bi-clock'}"></i> ${isPresent ? 'Presente' : 'Aguardando'}</span>`}
          </div>
        </div>

      `;
    }).join('');
  },

  async toggle(id, newStatus) {
    if (Auth.isObservador()) {
      App.showToast('O perfil de Observador não tem permissão para alterar presença.', 'danger');
      return;
    }
    try {
      const res = await API.toggleCheckin(id, newStatus);

      if (res.success) {
        App.showToast(res.message, newStatus ? 'success' : 'warning');
        await this.loadList();
        // Se a View de Dashboard estiver ativa, atualiza ela também
        if (DashboardView) DashboardView.loadData(true);
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao registrar presença.', 'danger');
    }
  }
};
