/**
 * View: Checklist de Ações Operacionais e Registro de Resultados
 */

const ChecklistView = {
  currentCategory: 'Pré-Evento',
  allTasks: [],

  async render() {
    this.bindEvents();
    await this.loadChecklist();
  },

  bindEvents() {
    const tabs = document.querySelectorAll('.checklist-tab');
    tabs.forEach(tab => {
      tab.onclick = () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        this.currentCategory = tab.dataset.category;
        this.renderCategoryTasks();
      };
    });

    const btnNew = document.getElementById('btn-nova-acao');
    if (btnNew) {
      btnNew.style.display = Auth.isOrganizador() ? 'inline-flex' : 'none';
      btnNew.onclick = () => this.openModal();
    }

  },

  async loadChecklist() {
    try {
      const res = await API.getChecklist();
      if (res.success) {
        this.allTasks = res.data;
        this.renderCategoryTasks();
      }
    } catch (err) {
      console.error("Erro ao carregar checklist:", err);
    }
  },

  renderCategoryTasks() {
    const tasks = this.allTasks.filter(t => t.categoria === this.currentCategory);
    const container = document.getElementById('checklist-tasks-container');
    if (!container) return;

    if (tasks.length === 0) {
      container.innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--text-muted); background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
          <i class="bi bi-card-checklist" style="font-size: 2.5rem; color: var(--text-dim);"></i>
          <p style="margin-top: 10px;">Nenhuma ação cadastrada para a fase: <strong>${this.currentCategory}</strong>.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = tasks.map(task => {
      let badgeClass = 'badge-warning';
      let statusText = 'Pendente';
      if (task.status === 'CONCLUIDO') { badgeClass = 'badge-success'; statusText = 'Concluído'; }
      if (task.status === 'EM_ANDAMENTO') { badgeClass = 'badge-info'; statusText = 'Em Andamento'; }

      const resultadoBlock = task.resultado_observacoes ? `
        <div class="task-result-box">
          <strong><i class="bi bi-file-earmark-check"></i> Resultado / Observação da Ação:</strong>
          <p style="margin-top: 4px;">${task.resultado_observacoes}</p>
          ${task.atualizado_por ? `<div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Registrado por ${task.atualizado_por} em ${new Date(task.atualizado_em).toLocaleString('pt-BR')}</div>` : ''}
        </div>
      ` : '';

      return `
        <div class="task-card">
          <div class="task-header">
            <div>
              <span class="badge ${badgeClass}" style="margin-bottom: 6px;">${statusText}</span>
              <h4 class="task-title">${task.titulo}</h4>
              ${task.descricao ? `<p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">${task.descricao}</p>` : ''}
              ${task.responsavel ? `<div style="font-size: 0.78rem; color: var(--accent-gold); margin-top: 4px;"><i class="bi bi-person-gear"></i> Responsável: ${task.responsavel}</div>` : ''}
            </div>
            <div style="display: flex; gap: 8px;">
              ${Auth.isOrganizador() ? `
                <button class="btn btn-secondary btn-sm" onclick="ChecklistView.openModal(${task.id})">
                  <i class="bi bi-pencil-square"></i> Registrar Resultado / Editar
                </button>
              ` : '<span class="badge badge-info"><i class="bi bi-eye"></i> Somente Leitura</span>'}
              ${Auth.isAdmin() ? `
                <button class="btn btn-danger btn-sm" onclick="ChecklistView.deleteTask(${task.id})">
                  <i class="bi bi-trash"></i>
                </button>
              ` : ''}
            </div>

          </div>
          ${resultadoBlock}
        </div>
      `;
    }).join('');
  },

  openModal(id = null) {
    if (Auth.isObservador()) {
      App.showToast('O perfil de Observador não tem permissão para editar o checklist.', 'danger');
      return;
    }
    const modal = document.getElementById('modal-checklist');
    const form = document.getElementById('form-checklist');
    form.reset();
    document.getElementById('task-id').value = '';
    document.getElementById('task-categoria').value = this.currentCategory;

    if (id) {
      const task = this.allTasks.find(t => t.id == id);
      if (task) {
        document.getElementById('task-id').value = task.id;
        document.getElementById('task-categoria').value = task.categoria;
        document.getElementById('task-titulo').value = task.titulo;
        document.getElementById('task-descricao').value = task.descricao || '';
        document.getElementById('task-responsavel').value = task.responsavel || '';
        document.getElementById('task-status').value = task.status;
        document.getElementById('task-resultado').value = task.resultado_observacoes || '';
      }
    }

    modal.classList.add('active');
  },

  closeModal() {
    document.getElementById('modal-checklist').classList.remove('active');
  },

  async saveTask(event) {
    event.preventDefault();
    if (Auth.isObservador()) {
      App.showToast('O perfil de Observador não tem permissão para alterar o checklist.', 'danger');
      return;
    }
    const taskData = {
      id: document.getElementById('task-id').value || null,
      categoria: document.getElementById('task-categoria').value,
      titulo: document.getElementById('task-titulo').value,
      descricao: document.getElementById('task-descricao').value,
      responsavel: document.getElementById('task-responsavel').value,
      status: document.getElementById('task-status').value,
      resultado_observacoes: document.getElementById('task-resultado').value
    };

    try {
      const res = await API.saveChecklistAction(taskData);
      if (res.success) {
        App.showToast(res.message, 'success');
        this.closeModal();
        await this.loadChecklist();
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao salvar ação.', 'danger');
    }
  },

  async deleteTask(id) {
    if (Auth.isObservador()) {
      App.showToast('O perfil de Observador não tem permissão para excluir ações.', 'danger');
      return;
    }
    if (!confirm('Tem certeza que deseja excluir esta ação do checklist?')) return;
    try {
      const res = await API.deleteChecklistAction(id);
      if (res.success) {
        App.showToast(res.message, 'success');
        await this.loadChecklist();
      }
    } catch (err) {
      App.showToast(err.message || 'Erro ao excluir.', 'danger');
    }
  }

};
