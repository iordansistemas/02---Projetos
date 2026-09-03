/**
 * View: Gestão de Usuários e Privilégios de Acesso (RBAC)
 */

const UsuariosView = {
  list: [],

  async render() {
    this.bindEvents();
    await this.loadUsuarios();
  },

  bindEvents() {
    const btnNew = document.getElementById('btn-novo-usuario');
    if (btnNew) {
      btnNew.onclick = () => this.openModal();
    }
  },

  async loadUsuarios() {
    try {
      const res = await API.getUsuarios();
      if (res.success) {
        this.list = res.data;
        this.renderTable();
      }
    } catch (err) {
      console.error("Erro ao carregar usuários:", err);
    }
  },

  renderTable() {
    const tbody = document.getElementById('usuarios-table-body');
    if (!tbody) return;

    if (this.list.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">
            Nenhum usuário cadastrado.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = this.list.map(u => {
      const roleUpper = (u.funcao || '').toUpperCase();
      let badgeRole = `<span class="badge badge-info"><i class="bi bi-person-check-fill"></i> Organizador / Recepção</span>`;
      if (roleUpper === 'ADMIN') {
        badgeRole = `<span class="badge badge-warning"><i class="bi bi-shield-lock-fill"></i> Administrador</span>`;
      } else if (roleUpper === 'OBSERVADOR') {
        badgeRole = `<span class="badge badge-danger" style="background: rgba(239, 68, 68, 0.2); color: #f87171;"><i class="bi bi-eye-fill"></i> Observador (Somente Dashboard)</span>`;
      }

      return `
        <tr>
          <td><strong>${u.nome}</strong></td>
          <td><code>${u.re_cpf}</code></td>
          <td>${badgeRole}</td>
          <td>${new Date(u.criado_em).toLocaleDateString('pt-BR')}</td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="UsuariosView.openModal(${u.id})">
              <i class="bi bi-pencil"></i> Editar
            </button>
            <button class="btn btn-danger btn-sm" onclick="UsuariosView.deleteUser(${u.id})">
              <i class="bi bi-trash"></i> Excluir
            </button>
          </td>
        </tr>
      `;
    }).join('');
  },


  openModal(id = null) {
    const modal = document.getElementById('modal-usuario');
    const form = document.getElementById('form-usuario');
    form.reset();
    document.getElementById('user-id').value = '';

    const labelSenha = document.getElementById('label-user-senha');
    const inputSenha = document.getElementById('user-senha');

    if (id) {
      const u = this.list.find(item => item.id == id);
      if (u) {
        document.getElementById('user-id').value = u.id;
        document.getElementById('user-nome').value = u.nome;
        document.getElementById('user-re-cpf').value = u.re_cpf;
        document.getElementById('user-funcao').value = u.funcao;
      }
      if (labelSenha) labelSenha.textContent = 'Senha de Acesso (Opcional):';
      if (inputSenha) {
        inputSenha.removeAttribute('required');
        inputSenha.placeholder = 'Deixe em branco para não alterar';
      }
    } else {
      if (labelSenha) labelSenha.textContent = 'Senha de Acesso: *';
      if (inputSenha) {
        inputSenha.setAttribute('required', 'required');
        inputSenha.placeholder = 'Digite a senha de acesso para o novo usuário';
      }
    }

    modal.classList.add('active');
  },


  closeModal() {
    document.getElementById('modal-usuario').classList.remove('active');
  },

  async saveUser(event) {
    event.preventDefault();
    const userData = {
      id: document.getElementById('user-id').value || null,
      nome: document.getElementById('user-nome').value,
      re_cpf: document.getElementById('user-re-cpf').value,
      senha: document.getElementById('user-senha').value,
      funcao: document.getElementById('user-funcao').value
    };

    try {
      const res = await API.saveUsuario(userData);
      if (res.success) {
        App.showToast(res.message, 'success');
        this.closeModal();
        await this.loadUsuarios();
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao salvar usuário.', 'danger');
    }
  },

  async deleteUser(id) {
    if (!confirm('Tem certeza que deseja excluir este usuário?')) return;
    try {
      const res = await API.deleteUsuario(id);
      if (res.success) {
        App.showToast(res.message, 'success');
        await this.loadUsuarios();
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao excluir.', 'danger');
    }
  }
};
