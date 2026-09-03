# 🎖️ Sistema PWA de Formatura de Entrega de Medalhas (PM)

Sistema web mobile-first desenvolvido especificamente para seções da Polícia Militar gerenciarem solenidades de entrega de condecorações e medalhas. O sistema funciona como um aplicativo PWA (Progressive Web App) no celular e pode ser facilmente hospedado em servidores de hospedagem compartilhada como a **HostGator**.

---

## 🚀 Principais Funcionalidades

1. **📱 PWA Modo Aplicativo no Celular**:
   - Instalável na tela inicial do celular (Android e iOS) sem passar por loja de aplicativos.
   - Design moderno com tema militar de alta visibilidade (Azul Marinho e Ouro) e suporte a modo offline.

2. **⚡ Controle de Presença em Tempo Real (Dia da Formatura)**:
   - Painel de recepção mobile para os organizadores marcarem a entrada de cada agraciado com apenas 1 toque.
   - Dashboard ao vivo exibindo instantaneamente **Foto**, **Nome**, **Posto/Graduação**, **Medalha** e horário de recepção para todos os usuários logados.

3. **✅ Ciência Prévia (RSVP do Agraciado)**:
   - Portal público onde o agraciado pesquisa pelo seu **RE** ou **CPF** para consultar seu convite, verificar seu assento/mesa e registrar sua confirmação de ciência prévia da formatura.

4. **📸 Cadastro Completo de Agraciados com Foto**:
   - Cadastro e edição de agraciados com suporte a upload de foto via galeria ou captura direta da câmera do celular.
   - Campos completos: RE, CPF, Nome Completo, Posto/Graduação, Unidade, Cargo, Medalha/Outorga, Nota CCOMSOC, Boletim de Publicação, Mesa/Setor.
   - Exportação completa da lista em formato **CSV (Excel)**.

5. **📋 Checklist de Ações Operacionais e Registro de Resultados**:
   - Divisão em 3 fases: **Pré-Evento**, **Dia da Formatura** e **Pós-Evento**.
   - Permite alterar status (Pendente, Em Andamento, Concluído) e registrar **resultados/observações de execução** (ex: *"Som testado às 07:30 - Todos os microfones OK"*, *"Mesa de medalhas conferida com o Livro de Ouro"*).

6. **👑 Gestão de Usuários e Níveis de Acesso (RBAC)**:
   - **Administrador**: Acesso total a configurações, gestão de usuários, cadastro de agraciados e exclusões.
   - **Organizador / Recepção**: Permissão para realizar check-in no dia, cadastrar agraciados e gerenciar o checklist operacional.
   - **Observador**: Permissão de **somente visualização** do painel em tempo real, lista de agraciados, fotos e checklist operacional (botões de edição e check-in ocultos).

---

## 🛠️ Como Testar Localmente no seu Computador

O sistema necessita do interpretador PHP para processar os arquivos `.php` da pasta `api/`. 

Já iniciamos o servidor PHP local para você na porta **8000**:
- **Link Local**: [http://localhost:8000](http://localhost:8000)

Se precisar reiniciar o servidor PHP manualmente no futuro, abra o PowerShell na pasta do projeto e execute:
```powershell
C:\tools\php\php.exe -S localhost:8000
```
E acesse no navegador: `http://localhost:8000`

> ⚠️ **Atenção**: Não utilize servidores puramente estáticos (como `python -m http.server`), pois eles não processam os arquivos `.php` do backend. Na HostGator, isso funciona automaticamente de forma nativa.


---

## ☁️ Guia Passo a Passo de Deploy na HostGator

O sistema foi desenhado em **PHP + SQLite/MySQL** para funcionar perfeitamente em qualquer plano de hospedagem da **HostGator** via cPanel.

### Passo 1: Enviar os arquivos para o servidor HostGator
1. Acesse o **cPanel da HostGator** e abra o **Gerenciador de Arquivos** (ou utilize um cliente FTP como o FileZilla).
2. Vá até a pasta `public_html/`.
3. Crie uma subpasta para o sistema (ex: `formatura` ou `medalhas`).
4. Faça o upload de todos os arquivos e pastas da diretório `aplicativo formatura`.

### Passo 2: Configurar permissões das pastas
Certifique-se de que as pastas a seguir possuem permissão de escrita (`755` ou `777` no cPanel):
- Pasta `api/` (para gravação do banco de dados).
- Pasta `uploads/` (para armazenamento das fotos dos agraciados).

### Passo 3: Inicializar o Banco de Dados
No seu navegador, acesse o link de instalação automática:
`https://seudominio.com.br/formatura/api/setup.php`

O script criará automaticamente as tabelas, as tarefas padrão do checklist e os usuários iniciais de acesso.

---

## 🔑 Credenciais de Acesso Padrão

- **Administrador (Acesso Total)**:
  - Usuário / RE / CPF: `admin`
  - Senha: `admin123`

- **Organizador / Recepção (Acesso ao Check-in e Edição)**:
  - Usuário / RE / CPF: `recepcao`
  - Senha: `recepcao123`

- **Observador (Apenas Visualização)**:
  - Usuário / RE / CPF: `observador`
  - Senha: `observador123`

*(Nota: Altere as senhas no painel de 'Usuários & Perfis' após o primeiro login).*

---

## 📱 Como instalar o PWA no Smartphone

1. No celular, acesse a URL do sistema no navegador (Chrome no Android ou Safari no iPhone).
2. No Android (Chrome): Clique no menu de três pontos no canto superior e selecione **"Adicionar à tela inicial"** ou **"Instalar aplicativo"**.
3. No iPhone (Safari): Clique no botão de compartilhamento (ícone de quadrado com seta para cima) e escolha **"Adicionar à Tela de Início"**.
