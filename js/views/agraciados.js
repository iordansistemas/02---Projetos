/**
 * View: Cadastro e Gerenciamento de Agraciados / Convidados
 */

const AgraciadosView = {
  currentData: [],

  async render() {
    this.bindEvents();
    await this.loadAgraciados();
  },

  bindEvents() {
    const searchInput = document.getElementById('search-agraciado');
    if (searchInput) {
      searchInput.oninput = () => this.filterData();
    }

    const btnNew = document.getElementById('btn-novo-agraciado');
    if (btnNew) {
      btnNew.style.display = Auth.isOrganizador() ? 'inline-flex' : 'none';
      btnNew.onclick = () => this.openModal();
    }


    const btnExport = document.getElementById('btn-export-csv');
    if (btnExport) {
      btnExport.onclick = () => {
        window.location.href = './api/agraciados.php?export=csv';
      };
    }
  },

  async loadAgraciados() {
    try {
      const res = await API.getAgraciados();
      if (res.success) {
        this.currentData = res.data;
        this.renderTable(this.currentData);
      }
    } catch (err) {
      console.error("Erro ao carregar lista de agraciados:", err);
    }
  },

  filterData() {
    const term = document.getElementById('search-agraciado').value.toLowerCase().trim();
    if (!term) {
      this.renderTable(this.currentData);
      return;
    }

    const filtered = this.currentData.filter(item => 
      (item.nome_completo && item.nome_completo.toLowerCase().includes(term)) ||
      (item.re && item.re.toLowerCase().includes(term)) ||
      (item.cpf && item.cpf.toLowerCase().includes(term)) ||
      (item.unidade && item.unidade.toLowerCase().includes(term)) ||
      (item.medalha && item.medalha.toLowerCase().includes(term))
    );

    this.renderTable(filtered);
  },

  renderTable(list) {
    const tbody = document.getElementById('agraciados-table-body');
    if (!tbody) return;

    if (list.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
            Nenhum agraciado encontrado.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = list.map(item => {
      const fotoHtml = item.foto_url
        ? `<img src="${item.foto_url}" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 1px solid var(--accent-gold);" alt="${item.nome_completo}">`
        : `<div style="width: 44px; height: 44px; border-radius: 50%; background: #1e293b; display: flex; align-items: center; justify-content: center; color: var(--accent-gold);"><i class="bi bi-person"></i></div>`;

      const badgeCiencia = item.confirmou_ciencia == 1
        ? `<span class="badge badge-success"><i class="bi bi-check-lg"></i> Confirmado</span>`
        : `<span class="badge badge-warning"><i class="bi bi-clock"></i> Pendente</span>`;

      const badgePresenca = item.presente_formatura == 1
        ? `<span class="badge badge-success"><i class="bi bi-person-check-fill"></i> Presente</span>`
        : `<span class="badge badge-danger"><i class="bi bi-x-circle"></i> Ausente</span>`;

      return `
        <tr>
          <td>${fotoHtml}</td>
          <td>
            <strong>${item.posto_graduacao} ${item.nome_completo}</strong><br>
            <small style="color: var(--text-muted);">RE: ${item.re || 'N/A'} | CPF: ${item.cpf || 'N/A'}</small>
          </td>
          <td>
            <span>${item.unidade}</span><br>
            <small style="color: var(--text-muted);">${item.cargo || ''}</small>
          </td>
          <td>
            <span style="color: var(--accent-gold); font-weight: 600;"><i class="bi bi-award"></i> ${item.medalha}</span><br>
            <small style="color: var(--text-muted);">${item.nota_ccomsoc || ''}</small>
          </td>
          <td>${badgeCiencia}</td>
          <td>${badgePresenca}</td>
          <td>
            ${Auth.isOrganizador() ? `
              <button class="btn btn-secondary btn-sm" onclick="AgraciadosView.openModal(${item.id})">
                <i class="bi bi-pencil"></i>
              </button>
            ` : '<span class="badge badge-info"><i class="bi bi-eye"></i> Visualização</span>'}
            ${Auth.isAdmin() ? `
              <button class="btn btn-danger btn-sm" onclick="AgraciadosView.deleteItem(${item.id})">
                <i class="bi bi-trash"></i>
              </button>
            ` : ''}
          </td>
        </tr>

      `;
    }).join('');
  },

  async openModal(id = null) {
    if (Auth.isObservador()) {
      App.showToast('O perfil de Observador não tem permissão para cadastrar ou editar agraciados.', 'danger');
      return;
    }
    const modal = document.getElementById('modal-agraciado');
    const form = document.getElementById('form-agraciado');
    form.reset();
    document.getElementById('agraciado-id').value = '';
    document.getElementById('photo-preview').src = 'https://img.icons8.com/color/96/user.png';
    document.getElementById('foto-base64-input').value = '';

    if (id) {
      try {
        const res = await API.getAgraciadoById(id);
        if (res.success) {
          const d = res.data;
          document.getElementById('agraciado-id').value = d.id;
          document.getElementById('agraciado-re').value = d.re || '';
          document.getElementById('agraciado-cpf').value = d.cpf || '';
          document.getElementById('agraciado-nome').value = d.nome_completo || '';
          document.getElementById('agraciado-posto').value = d.posto_graduacao || '';
          document.getElementById('agraciado-unidade').value = d.unidade || '';
          document.getElementById('agraciado-cargo').value = d.cargo || '';
          document.getElementById('agraciado-medalha').value = d.medalha || '';
          document.getElementById('agraciado-nota').value = d.nota_ccomsoc || '';
          document.getElementById('agraciado-boletim').value = d.boletim_publicacao || '';
          document.getElementById('agraciado-mesa').value = d.mesa_setor || '';
          if (d.foto_url) {
            document.getElementById('photo-preview').src = d.foto_url;
          }
        }
      } catch (err) {
        console.error("Erro ao carregar dados do agraciado:", err);
      }
    }

    modal.classList.add('active');
  },

  closeModal() {
    document.getElementById('modal-agraciado').classList.remove('active');
  },

  async saveForm(event) {
    event.preventDefault();
    if (Auth.isObservador()) {
      App.showToast('O perfil de Observador não tem permissão para salvar agraciados.', 'danger');
      return;
    }
    const form = document.getElementById('form-agraciado');
    const formData = new FormData(form);

    try {
      const res = await API.saveAgraciadoFormData(formData);
      if (res.success) {
        App.showToast(res.message, 'success');
        this.closeModal();
        await this.loadAgraciados();
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao salvar agraciado.', 'danger');
    }
  },

  async deleteItem(id) {
    if (Auth.isObservador()) {
      App.showToast('O perfil de Observador não tem permissão para excluir agraciados.', 'danger');
      return;
    }
    if (!confirm('Tem certeza que deseja excluir este agraciado?')) return;
    try {
      const res = await API.deleteAgraciado(id);
      if (res.success) {
        App.showToast(res.message, 'success');
        await this.loadAgraciados();
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao excluir.', 'danger');
    }
  }

};
