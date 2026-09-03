/**
 * View: Portal de Confirmação Prévia e Ciência do Agraciado (RSVP)
 */

const RsvpView = {
  currentAgraciado: null,

  bindEvents() {
    const btnSearch = document.getElementById('btn-rsvp-search');
    if (btnSearch) {
      btnSearch.onclick = () => this.search();
    }

    const inputSearch = document.getElementById('rsvp-search-input');
    if (inputSearch) {
      inputSearch.onkeypress = (e) => {
        if (e.key === 'Enter') this.search();
      };
    }
  },

  async search() {
    const termo = document.getElementById('rsvp-search-input').value.trim();
    const resultBox = document.getElementById('rsvp-result-box');
    resultBox.style.display = 'none';

    if (!termo) {
      App.showToast('Digite o seu RE ou CPF para consultar o convite.', 'warning');
      return;
    }

    try {
      const res = await API.searchRSVP(termo);
      if (res.success) {
        this.currentAgraciado = res.data;
        this.renderResult(res.data);
      }
    } catch (err) {
      App.showToast(err.message || 'Convite não localizado.', 'danger');
    }
  },

  renderResult(data) {
    const resultBox = document.getElementById('rsvp-result-box');
    resultBox.style.display = 'block';

    const fotoHtml = data.foto_url
      ? `<img src="${data.foto_url}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-gold); margin: 0 auto 16px; display: block;">`
      : `<div style="width: 100px; height: 100px; border-radius: 50%; background: #1e293b; color: var(--accent-gold); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; border: 3px solid var(--accent-gold); margin: 0 auto 16px;"><i class="bi bi-award"></i></div>`;

    const statusBadge = data.confirmou_ciencia == 1
      ? `<div class="badge badge-success" style="font-size: 0.9rem; padding: 6px 14px; margin-bottom: 16px;"><i class="bi bi-check-circle-fill"></i> Ciência Confirmada em ${new Date(data.data_ciencia).toLocaleDateString('pt-BR')}</div>`
      : `<div class="badge badge-warning" style="font-size: 0.9rem; padding: 6px 14px; margin-bottom: 16px;"><i class="bi bi-clock-history"></i> Aguardando Ciência do Agraciado</div>`;

    resultBox.innerHTML = `
      <div style="background: var(--bg-card); border: 1px solid var(--border-gold); border-radius: var(--radius-lg); padding: 24px; text-align: center; box-shadow: var(--shadow-lg);">
        ${fotoHtml}
        ${statusBadge}
        <h3 style="font-size: 1.3rem; color: #fff; margin-bottom: 4px;">${data.posto_graduacao} ${data.nome_completo}</h3>
        <p style="color: var(--accent-gold); font-weight: 600; font-size: 1.1rem; margin-bottom: 16px;"><i class="bi bi-award-fill"></i> Outorga: ${data.medalha}</p>
        
        <div style="background: rgba(15, 23, 42, 0.6); padding: 16px; border-radius: var(--radius-md); text-align: left; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid var(--border-color);">
          <p style="margin-bottom: 6px;"><strong>RE:</strong> ${data.re || 'N/A'} | <strong>CPF:</strong> ${data.cpf || 'N/A'}</p>
          <p style="margin-bottom: 6px;"><strong>Unidade:</strong> ${data.unidade} - ${data.cargo || ''}</p>
          ${data.nota_ccomsoc ? `<p style="margin-bottom: 6px;"><strong>Publicação:</strong> ${data.nota_ccomsoc} (${data.boletim_publicacao || ''})</p>` : ''}
          ${data.mesa_setor ? `<p style="margin-bottom: 0; color: var(--accent-gold); font-weight: 700;"><strong>Local na Solenidade:</strong> ${data.mesa_setor}</p>` : ''}
        </div>

        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
          Por favor, confirme abaixo a ciência do convite para a solenidade de entrega de medalhas.
        </p>

        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
          <button class="btn btn-primary btn-lg" onclick="RsvpView.confirm(1)">
            <i class="bi bi-check-circle-fill"></i> Dar Ciência / Confirmar Presença
          </button>
          <button class="btn btn-secondary btn-lg" onclick="RsvpView.confirm(0)">
            <i class="bi bi-x-circle"></i> Não Poderei Comparecer
          </button>
        </div>
      </div>
    `;
  },

  async confirm(confirmou) {
    if (!this.currentAgraciado) return;
    try {
      const res = await API.confirmRSVP(this.currentAgraciado.id, confirmou);
      if (res.success) {
        App.showToast(res.message, confirmou ? 'success' : 'warning');
        await this.search();
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao registrar ciência.', 'danger');
    }
  }
};
