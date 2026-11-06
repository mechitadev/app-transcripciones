/**
 * Cliente Frontend · App Transcripciones (Tailwind CSS v4 + Vanilla ES6+)

 */
(function () {
    'use strict';

    const stateNode = document.getElementById('initial-app-state');
    if (!stateNode) return;

    const initialConfig = JSON.parse(stateNode.textContent || '{}');
    const baseUrl = initialConfig.baseUrl || '';
    const i18n = initialConfig.i18n || {};

    const state = {
        meetings: Array.isArray(initialConfig.meetings) ? initialConfig.meetings : [],
        meeting: null,
        entries: [],
        tasks: [],
        decisions: [],
        activeTab: 'minutes',
        selectedSpeaker: 'all',
        taskStatusFilter: 'all',
        decisionCategoryFilter: '',
        decisionScope: 'current',
        decisionSearchQuery: '',
        transcriptFilterText: '',
        sidebarSearchText: '',
        pendingDeleteMeetingId: null,
        selectedUploadFile: null,
        searchAbortController: null,
        debounceTimer: null,
        highlightTimer: null,
        bannerTimer: null
    };

    const dom = {
        sidebar: document.getElementById('workspace-sidebar'),
        btnSidebarCollapse: document.getElementById('btn-sidebar-collapse'),
        btnSidebarExpand: document.getElementById('btn-sidebar-expand'),
        btnThemeToggle: document.getElementById('btn-theme-toggle'),
        sidebarMeetingsCount: document.getElementById('sidebar-meetings-count'),
        sidebarMeetingFilter: document.getElementById('sidebar-meeting-filter'),
        meetingsList: document.getElementById('meetings-list'),
        meetingSelect: document.getElementById('meeting-select'),

        btnUploadTxt: document.getElementById('btn-upload-txt'),
        btnDeleteMeeting: document.getElementById('btn-delete-meeting'),
        btnExportMarkdown: document.getElementById('btn-export-markdown'),
        btnToggleViewerMobile: document.getElementById('btn-toggle-viewer-mobile'),
        btnCloseViewerMobile: document.getElementById('btn-close-viewer-mobile'),

        uploadDialog: document.getElementById('upload-dialog'),
        formUploadModal: document.getElementById('form-upload-modal'),
        fileInput: document.getElementById('file-upload-input'),
        uploadDropzone: document.getElementById('upload-dropzone'),
        uploadSelectedFilename: document.getElementById('upload-selected-filename'),
        uploadCustomTitle: document.getElementById('upload-custom-title'),
        btnUploadSubmit: document.getElementById('btn-upload-submit'),

        confirmDeleteDialog: document.getElementById('confirm-delete-dialog'),
        btnConfirmDeleteMeeting: document.getElementById('btn-confirm-delete-meeting'),

        statusBanner: document.getElementById('status-banner'),

        meetingTitleDisplay: document.getElementById('meeting-title-display'),
        meetingTitleInput: document.getElementById('meeting-title-input'),
        btnEditMeetingTitle: document.getElementById('btn-edit-meeting-title'),

        metaStartedAt: document.getElementById('meta-started-at'),
        metaDuration: document.getElementById('meta-duration'),
        metaSourceFile: document.getElementById('meta-source-file'),
        metaAiModel: document.getElementById('meta-ai-model'),
        metaUrlWrap: document.getElementById('meta-url-wrap'),
        metaOriginalUrl: document.getElementById('meta-original-url'),
        speakerChips: document.getElementById('speaker-chips'),

        tabBtnMinutes: document.getElementById('tab-btn-minutes'),
        tabBtnDecisions: document.getElementById('tab-btn-decisions'),
        tabCountTasks: document.getElementById('tab-count-tasks'),
        tabCountDecisions: document.getElementById('tab-count-decisions'),
        btnShortcutCtrlK: document.getElementById('btn-shortcut-ctrlk'),
        panelModuleMinutes: document.getElementById('panel-module-minutes'),
        panelModuleDecisions: document.getElementById('panel-module-decisions'),

        executiveSummaryText: document.getElementById('executive-summary-text'),
        btnEditSummary: document.getElementById('btn-edit-summary'),
        summaryEditorWrap: document.getElementById('summary-editor-wrap'),
        executiveSummaryInput: document.getElementById('executive-summary-input'),
        btnSaveSummary: document.getElementById('btn-save-summary'),
        btnCancelSummary: document.getElementById('btn-cancel-summary'),

        tasksProgressText: document.getElementById('tasks-progress-text'),
        tasksProgressBar: document.getElementById('tasks-progress-bar'),
        formCreateTask: document.getElementById('form-create-task'),
        newTaskSummary: document.getElementById('new-task-summary'),
        newTaskAssignee: document.getElementById('new-task-assignee'),
        newTaskTimestamp: document.getElementById('new-task-timestamp'),
        taskStatusFilters: document.getElementById('task-status-filters'),
        tasksList: document.getElementById('tasks-list'),

        decisionScopeToggle: document.getElementById('decision-scope-toggle'),
        decisionSearchInput: document.getElementById('decision-search-input'),
        semanticConceptsChips: document.getElementById('semantic-concepts-chips'),
        decisionCategoryFilters: document.getElementById('decision-category-filters'),
        expandedTermsBar: document.getElementById('expanded-terms-bar'),
        detailsNewDecision: document.getElementById('details-new-decision'),
        formCreateDecision: document.getElementById('form-create-decision'),
        newDecisionTitle: document.getElementById('new-decision-title'),
        newDecisionCategory: document.getElementById('new-decision-category'),
        newDecisionSpeaker: document.getElementById('new-decision-speaker'),
        newDecisionRationale: document.getElementById('new-decision-rationale'),
        newDecisionTimestamp: document.getElementById('new-decision-timestamp'),
        newDecisionTags: document.getElementById('new-decision-tags'),
        decisionsList: document.getElementById('decisions-list'),

        viewerPanel: document.getElementById('transcript-viewer-panel'),
        transcriptFilterInput: document.getElementById('transcript-filter-input'),
        speakerFilterBanner: document.getElementById('speaker-filter-banner'),
        speakerFilterName: document.getElementById('speaker-filter-name'),
        btnClearSpeakerFilter: document.getElementById('btn-clear-speaker-filter'),
        transcriptList: document.getElementById('transcript-list'),
        viewerCountNum: document.getElementById('viewer-count-num')
    };

    function buildHeaders(isJson = true) {
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (isJson) {
            headers['Content-Type'] = 'application/json';
        }
        const jwt = localStorage.getItem('jwt_token');
        if (jwt) {
            headers['Authorization'] = 'Bearer ' + jwt;
        }
        return headers;
    }

    function showBanner(message) {
        if (!dom.statusBanner || !message) return;
        dom.statusBanner.textContent = message;
        dom.statusBanner.hidden = false;
        if (state.bannerTimer) {
            clearTimeout(state.bannerTimer);
        }
        state.bannerTimer = setTimeout(function () {
            dom.statusBanner.hidden = true;
        }, 4000);
    }

    function switchModuleTab(tabName) {
        state.activeTab = tabName === 'decisions' ? 'decisions' : 'minutes';
        const isMinutes = state.activeTab === 'minutes';

        if (dom.tabBtnMinutes && dom.tabBtnDecisions) {
            dom.tabBtnMinutes.setAttribute('aria-selected', isMinutes ? 'true' : 'false');
            dom.tabBtnDecisions.setAttribute('aria-selected', !isMinutes ? 'true' : 'false');

            dom.tabBtnMinutes.className = isMinutes
                ? 'inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition-colors duration-300 dark:bg-[#272727] dark:text-white'
                : 'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors duration-300 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-[#272727] dark:hover:text-white';

            dom.tabBtnDecisions.className = !isMinutes
                ? 'inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition-colors duration-300 dark:bg-[#272727] dark:text-white'
                : 'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors duration-300 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-[#272727] dark:hover:text-white';
        }

        if (dom.panelModuleMinutes) dom.panelModuleMinutes.hidden = !isMinutes;
        if (dom.panelModuleDecisions) dom.panelModuleDecisions.hidden = isMinutes;
    }

    function hydrateMeetingData(data) {
        if (!data || !data.meeting) {
            state.meeting = null;
            state.entries = [];
            state.tasks = [];
            state.decisions = [];
            state.selectedSpeaker = 'all';
            renderAll();
            return;
        }

        state.meeting = data.meeting;
        state.entries = Array.isArray(data.entries) ? data.entries : [];
        state.tasks = Array.isArray(data.tasks) ? data.tasks : [];
        state.decisions = Array.isArray(data.decisions) ? data.decisions : [];
        state.selectedSpeaker = 'all';

        if (dom.meetingSelect && state.meeting.id) {
            dom.meetingSelect.value = String(state.meeting.id);
        }

        renderAll();
    }

    function renderAll() {
        renderSidebarMeetings();
        renderMetadataAndSpeakers();
        renderTasks();
        renderDecisions(state.decisions, []);
        renderTranscript();
    }

    /**
     * Renderiza la lista de reuniones en el sidebar izquierdo con DocumentFragment.
     */
    function renderSidebarMeetings() {
        if (!dom.meetingsList) return;
        dom.meetingsList.textContent = '';

        const filterText = state.sidebarSearchText.trim().toLowerCase();
        const filtered = state.meetings.filter(function (m) {
            if (!filterText) return true;
            const hay = ((m.title || m.titulo || '') + ' ' + (m.source_filename || m.nombre_archivo_origen || '')).toLowerCase();
            return hay.includes(filterText);
        });

        if (dom.sidebarMeetingsCount) {
            dom.sidebarMeetingsCount.textContent = String(state.meetings.length);
        }

        if (filtered.length === 0) {
            const emptyLi = document.createElement('li');
            emptyLi.className = 'rounded-xl border border-slate-200 bg-slate-50 p-3 text-center text-xs text-slate-500 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-400';
            emptyLi.textContent = i18n.sidebarEmptySearch || i18n.emptyMeetingState || '';
            dom.meetingsList.appendChild(emptyLi);
            return;
        }

        const activeId = state.meeting ? Number(state.meeting.id) : 0;
        const frag = document.createDocumentFragment();

        filtered.forEach(function (m) {
            const mId = Number(m.id);
            const isActive = mId === activeId;

            const li = document.createElement('li');
            li.className = isActive
                ? 'group flex items-center justify-between gap-2 rounded-xl border border-emerald-500/50 bg-emerald-500/10 p-2.5 transition-colors duration-300 dark:border-emerald-500/50 dark:bg-[#272727]'
                : 'group flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5 transition-colors duration-300 hover:bg-slate-100 dark:border-[#313131] dark:bg-[#181818] dark:hover:bg-[#272727]';

            const selectBtn = document.createElement('button');
            selectBtn.type = 'button';
            selectBtn.className = 'min-w-0 flex-1 text-left focus:outline-none';
            selectBtn.dataset.selectMeetingId = String(mId);

            const titleP = document.createElement('p');
            titleP.className = isActive
                ? 'truncate text-xs font-bold text-emerald-700 dark:text-emerald-300'
                : 'truncate text-xs font-semibold text-slate-800 dark:text-zinc-200';
            titleP.textContent = m.title || m.titulo || '';

            const subP = document.createElement('p');
            subP.className = 'mt-0.5 truncate font-mono text-[11px] text-slate-500 dark:text-zinc-400';
            const dur = m.duration_minutes ?? m.duracion_minutos ?? 0;
            const file = m.source_filename || m.nombre_archivo_origen || '';
            subP.textContent = dur + ' min · ' + file;

            selectBtn.appendChild(titleP);
            selectBtn.appendChild(subP);

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'shrink-0 rounded-lg p-1 text-slate-400 opacity-0 transition-opacity duration-300 hover:bg-red-500/10 hover:text-red-600 group-hover:opacity-100 focus:opacity-100 dark:text-zinc-500 dark:hover:text-red-400';
            delBtn.dataset.deleteMeetingId = String(mId);
            delBtn.title = i18n.navDeleteMeeting || '';
            delBtn.setAttribute('aria-label', i18n.navDeleteMeeting || '');
            delBtn.textContent = '×';

            li.appendChild(selectBtn);
            li.appendChild(delBtn);
            frag.appendChild(li);
        });

        dom.meetingsList.appendChild(frag);
    }

    /**
     * Renderiza la cabecera de la reunión activa, chips de participantes con conteo real y selectores de hablante.
     */
    function renderMetadataAndSpeakers() {
        const m = state.meeting;
        if (!m) {
            if (dom.meetingTitleDisplay) dom.meetingTitleDisplay.textContent = 'Sin reunión seleccionada';
            if (dom.btnEditMeetingTitle) dom.btnEditMeetingTitle.hidden = true;
            if (dom.btnExportMarkdown) dom.btnExportMarkdown.hidden = true;
            if (dom.btnDeleteMeeting) dom.btnDeleteMeeting.hidden = true;
            if (dom.btnEditSummary) dom.btnEditSummary.hidden = true;
            if (dom.metaStartedAt) dom.metaStartedAt.textContent = '—';
            if (dom.metaDuration) dom.metaDuration.textContent = '0';
            if (dom.metaSourceFile) dom.metaSourceFile.textContent = '—';
            if (dom.metaAiModel) dom.metaAiModel.textContent = '—';
            if (dom.metaUrlWrap) dom.metaUrlWrap.hidden = true;
            if (dom.executiveSummaryText) dom.executiveSummaryText.textContent = i18n.emptyMeetingState || '';
            if (dom.speakerChips) dom.speakerChips.textContent = '';
            if (dom.newTaskAssignee) dom.newTaskAssignee.textContent = '';
            if (dom.newDecisionSpeaker) dom.newDecisionSpeaker.textContent = '';
            return;
        }

        if (dom.btnEditMeetingTitle) dom.btnEditMeetingTitle.hidden = false;
        if (dom.btnExportMarkdown) dom.btnExportMarkdown.hidden = false;
        if (dom.btnDeleteMeeting) dom.btnDeleteMeeting.hidden = false;
        if (dom.btnEditSummary) dom.btnEditSummary.hidden = false;

        if (dom.meetingTitleDisplay) {
            dom.meetingTitleDisplay.textContent = m.title || m.titulo || '—';
        }
        if (dom.metaStartedAt) dom.metaStartedAt.textContent = m.started_at || '—';
        if (dom.metaDuration) dom.metaDuration.textContent = String(m.duration_minutes || 0);
        if (dom.metaSourceFile) dom.metaSourceFile.textContent = m.source_filename || '—';
        if (dom.metaAiModel) dom.metaAiModel.textContent = m.ai_model_used || 'heuristic-local';
        if (dom.executiveSummaryText) dom.executiveSummaryText.textContent = m.executive_summary || '—';

        if (dom.btnExportMarkdown && m.id) {
            dom.btnExportMarkdown.href = baseUrl + '/api/meetings/' + encodeURIComponent(String(m.id)) + '/export';
        }

        if (dom.metaUrlWrap && dom.metaOriginalUrl) {
            if (m.original_url) {
                dom.metaOriginalUrl.href = m.original_url;
                dom.metaUrlWrap.hidden = false;
            } else {
                dom.metaUrlWrap.hidden = true;
            }
        }

        // Conteo real de intervenciones por participante en O(n)
        const speakerCounts = {};
        state.entries.forEach(function (entry) {
            const sp = entry.speaker || '';
            if (sp) {
                speakerCounts[sp] = (speakerCounts[sp] || 0) + 1;
            }
        });

        const participants = Array.isArray(m.participants) ? m.participants.slice() : [];
        Object.keys(speakerCounts).forEach(function (sp) {
            if (!participants.includes(sp)) {
                participants.push(sp);
            }
        });

        // Chips de filtro por hablante
        if (dom.speakerChips) {
            const chipFrag = document.createDocumentFragment();
            const isAll = state.selectedSpeaker === 'all';
            const allChip = document.createElement('button');
            allChip.type = 'button';
            allChip.dataset.speaker = 'all';
            allChip.setAttribute('aria-pressed', isAll ? 'true' : 'false');
            allChip.className = isAll
                ? 'speaker-chip shrink-0 rounded-lg border border-emerald-500/50 bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300'
                : 'speaker-chip shrink-0 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300 dark:hover:bg-[#313131]';
            allChip.textContent = (i18n.metaAllParticipants || 'Todos') + ' (' + state.entries.length + ')';
            chipFrag.appendChild(allChip);

            participants.forEach(function (speaker) {
                const active = state.selectedSpeaker === speaker;
                const count = speakerCounts[speaker] || 0;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.speaker = speaker;
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                btn.className = active
                    ? 'speaker-chip shrink-0 rounded-lg border border-emerald-500/50 bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300'
                    : 'speaker-chip shrink-0 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300 dark:hover:bg-[#313131]';
                btn.textContent = speaker + ' (' + count + ')';
                chipFrag.appendChild(btn);
            });

            dom.speakerChips.textContent = '';
            dom.speakerChips.appendChild(chipFrag);
        }

        // Banner de filtro de hablante en el visor
        if (dom.speakerFilterBanner && dom.speakerFilterName) {
            if (state.selectedSpeaker !== 'all') {
                dom.speakerFilterName.textContent = state.selectedSpeaker;
                dom.speakerFilterBanner.hidden = false;
            } else {
                dom.speakerFilterBanner.hidden = true;
            }
        }

        // Poblar selectores de responsable (Tareas y Decisiones)
        const defaultTeam = i18n.taskDefaultAssignee || 'Equipo';
        const pool = participants.slice();
        if (!pool.includes(defaultTeam)) {
            pool.push(defaultTeam);
        }

        [dom.newTaskAssignee, dom.newDecisionSpeaker].forEach(function (selectEl) {
            if (!selectEl) return;
            const frag = document.createDocumentFragment();
            pool.forEach(function (person) {
                const opt = document.createElement('option');
                opt.value = person;
                opt.textContent = person;
                frag.appendChild(opt);
            });
            selectEl.textContent = '';
            selectEl.appendChild(frag);
        });
    }

    /**
     * Cierra cualquier menú desplegable activo en la lista de tareas.
     */
    function closeAllTaskDropdowns() {
        document.querySelectorAll('.js-status-menu:not(.hidden), .js-assignee-menu:not(.hidden)').forEach(function (m) {
            m.classList.add('hidden');
            const trigger = m.parentElement ? m.parentElement.querySelector('button[aria-expanded="true"]') : null;
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    }

    /**
     * Guarda la edición del contenido de una tarea (Módulo 1).
     */
    async function saveTaskSummary(taskId, newSummary, row) {
        const pEl = row ? row.querySelector('.task-summary-text') : null;
        const editWrap = row ? row.querySelector('.js-task-edit-wrap') : null;
        const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });

        if (editWrap) editWrap.classList.add('hidden');
        if (pEl) pEl.hidden = false;

        if (!newSummary || !found || newSummary === found.summary) return;

        const response = await fetch(baseUrl + '/api/tasks/' + encodeURIComponent(taskId), {
            method: 'PATCH',
            headers: buildHeaders(true),
            body: JSON.stringify({ summary: newSummary })
        });
        if (response.ok) {
            found.summary = newSummary;
            if (pEl) pEl.textContent = newSummary;
            showBanner(i18n.taskUpdatedMsg || '');
        }
    }

    /**
     * Renderiza el progreso y la lista de tareas (Módulo 1) con DocumentFragment.
     */
    function renderTasks() {
        if (!dom.tasksList) return;
        dom.tasksList.textContent = '';

        const totalTasks = state.tasks.length;
        let doneCount = 0;
        let pendingCount = 0;
        let inProgressCount = 0;

        state.tasks.forEach(function (t) {
            if (t.status === 'done') doneCount++;
            else if (t.status === 'in_progress') inProgressCount++;
            else pendingCount++;
        });

        const pct = totalTasks > 0 ? Math.round((doneCount / totalTasks) * 100) : 0;
        const ratio = totalTasks > 0 ? (doneCount / totalTasks) : 0;

        if (dom.tabCountTasks) {
            dom.tabCountTasks.textContent = String(totalTasks);
        }
        if (dom.tasksProgressText) {
            dom.tasksProgressText.textContent = doneCount + ' / ' + totalTasks + ' ' + (i18n.tasksCompletedOf || 'completadas') + ' · ' + pct + '%';
        }
        if (dom.tasksProgressBar) {
            dom.tasksProgressBar.style.transform = 'scaleX(' + ratio + ')';
        }

        // Actualizar contadores de filtros de estado
        if (dom.taskStatusFilters) {
            const countsMap = {
                all: totalTasks,
                pending: pendingCount,
                in_progress: inProgressCount,
                done: doneCount
            };
            Object.keys(countsMap).forEach(function (key) {
                const badge = dom.taskStatusFilters.querySelector('[data-count-status="' + key + '"]');
                if (badge) badge.textContent = String(countsMap[key]);
            });
        }

        const participants = (state.meeting && Array.isArray(state.meeting.participants))
            ? state.meeting.participants.slice()
            : [];
        const defaultTeam = i18n.taskDefaultAssignee || 'Equipo';
        if (!participants.includes(defaultTeam)) {
            participants.push(defaultTeam);
        }

        const filtered = state.tasks.filter(function (task) {
            if (state.taskStatusFilter !== 'all' && task.status !== state.taskStatusFilter) {
                return false;
            }
            if (state.selectedSpeaker !== 'all' && task.assignee !== state.selectedSpeaker) {
                return false;
            }
            return true;
        });

        if (filtered.length === 0) {
            const emptyLi = document.createElement('li');
            emptyLi.className = 'rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500 dark:border-[#313131] dark:bg-[#181818] dark:text-zinc-400';
            emptyLi.textContent = i18n.emptyTasks || '';
            dom.tasksList.appendChild(emptyLi);
            return;
        }

        const frag = document.createDocumentFragment();
        filtered.forEach(function (task) {
            const isDone = task.status === 'done';
            const li = document.createElement('li');
            li.className = 'task-row rounded-xl border border-slate-200 bg-slate-50 p-3.5 transition-colors duration-300 dark:border-[#313131] dark:bg-[#181818]';
            li.dataset.taskId = String(task.id);
            li.dataset.status = task.status || 'pending';

            const topRow = document.createElement('div');
            topRow.className = 'flex items-start gap-3';

            // Checkbox 1 clic personalizado (SaaS Style)
            const checkInput = document.createElement('input');
            checkInput.type = 'checkbox';
            checkInput.checked = isDone;
            checkInput.dataset.taskId = String(task.id);
            checkInput.setAttribute('aria-label', i18n.taskToggleDoneLabel || 'Marcar como completada');
            checkInput.className = 'app-checkbox js-toggle-task-done mt-0.5';

            // Contenedor de texto editable inline con visualización completa
            const textWrap = document.createElement('div');
            textWrap.className = 'min-w-0 flex-1';

            const summaryP = document.createElement('p');
            summaryP.className = isDone
                ? 'task-summary-text text-sm font-medium text-slate-400 line-through dark:text-zinc-500'
                : 'task-summary-text text-sm font-medium text-slate-900 dark:text-zinc-100';
            summaryP.textContent = task.summary || '';
            summaryP.dataset.taskId = String(task.id);

            // Editor expandido con Textarea para lectura y edición óptima
            const editWrap = document.createElement('div');
            editWrap.className = 'js-task-edit-wrap hidden mt-1 space-y-2';

            const editTextarea = document.createElement('textarea');
            editTextarea.className = 'js-task-summary-textarea w-full rounded-xl border border-emerald-500 bg-white p-2.5 text-sm leading-relaxed text-slate-900 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-emerald-500/70 dark:bg-[#202020] dark:text-zinc-100 resize-y';
            editTextarea.rows = 3;
            editTextarea.value = task.summary || '';
            editTextarea.dataset.taskId = String(task.id);
            editTextarea.setAttribute('aria-label', i18n.taskEditBtn || 'Editar contenido de la tarea');

            const editActions = document.createElement('div');
            editActions.className = 'flex flex-wrap items-center justify-between gap-2';

            const editTip = document.createElement('span');
            editTip.className = 'font-mono text-[11px] text-slate-400 dark:text-zinc-500';
            editTip.textContent = 'Ctrl + Enter para guardar · Esc para cancelar';

            const editBtnsWrap = document.createElement('div');
            editBtnsWrap.className = 'flex items-center gap-1.5 ml-auto';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'js-cancel-task-edit rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:border-[#383838] dark:bg-[#282828] dark:text-zinc-300 dark:hover:bg-[#313131] transition-colors';
            cancelBtn.dataset.taskId = String(task.id);
            cancelBtn.textContent = 'Cancelar';

            const saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.className = 'js-save-task-edit rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-500 transition-colors shadow-xs';
            saveBtn.dataset.taskId = String(task.id);
            saveBtn.textContent = 'Guardar';

            editBtnsWrap.appendChild(cancelBtn);
            editBtnsWrap.appendChild(saveBtn);

            editActions.appendChild(editTip);
            editActions.appendChild(editBtnsWrap);

            editWrap.appendChild(editTextarea);
            editWrap.appendChild(editActions);

            textWrap.appendChild(summaryP);
            textWrap.appendChild(editWrap);

            topRow.appendChild(checkInput);
            topRow.appendChild(textWrap);

            // Fila inferior de controles
            const controls = document.createElement('div');
            controls.className = 'mt-3 flex flex-wrap items-center justify-between gap-2 pl-7';

            const leftGroup = document.createElement('div');
            leftGroup.className = 'flex flex-wrap items-center gap-2';

            // Map de configuración visual para Estados de Tareas
            const statusMap = {
                pending: {
                    label: i18n.statusPending || 'Pendiente',
                    dot: 'bg-amber-400 dark:bg-amber-400',
                    pill: 'border-amber-500/25 bg-amber-500/10 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300 hover:border-amber-500/40'
                },
                in_progress: {
                    label: i18n.statusInProgress || 'En progreso',
                    dot: 'bg-sky-400 animate-pulse',
                    pill: 'border-sky-500/30 bg-sky-500/10 text-sky-800 dark:border-sky-400/25 dark:bg-sky-400/15 dark:text-sky-300 hover:border-sky-500/50'
                },
                done: {
                    label: i18n.statusDone || 'Completada',
                    dot: 'bg-emerald-500',
                    pill: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/15 dark:text-emerald-300 hover:border-emerald-500/50'
                }
            };

            const currentStatus = statusMap[task.status] || statusMap.pending;

            // Dropdown Custom de Estado (SaaS style, sin selects nativos del SO)
            const statusWrap = document.createElement('div');
            statusWrap.className = 'relative inline-block text-left js-status-dropdown';

            const statusBtn = document.createElement('button');
            statusBtn.type = 'button';
            statusBtn.className = 'js-status-dropdown-trigger inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold shadow-xs transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 ' + currentStatus.pill;
            statusBtn.dataset.taskId = String(task.id);
            statusBtn.setAttribute('aria-haspopup', 'listbox');
            statusBtn.setAttribute('aria-expanded', 'false');
            statusBtn.setAttribute('aria-label', (i18n.statusPending || 'Estado') + ': ' + currentStatus.label);

            const statusDot = document.createElement('span');
            statusDot.className = 'inline-block h-2 w-2 rounded-full ' + currentStatus.dot;
            statusDot.setAttribute('aria-hidden', 'true');

            const statusText = document.createElement('span');
            statusText.textContent = currentStatus.label;

            statusBtn.appendChild(statusDot);
            statusBtn.appendChild(statusText);
            statusBtn.insertAdjacentHTML('beforeend', '<svg class="h-3 w-3 opacity-60 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>');

            const statusMenu = document.createElement('div');
            statusMenu.className = 'js-status-menu dropdown-menu-popover hidden absolute left-0 top-full mt-1.5 z-40 w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-2xl dark:border-[#383838] dark:bg-[#202020]';
            statusMenu.setAttribute('role', 'listbox');

            [
                { value: 'pending', key: 'pending' },
                { value: 'in_progress', key: 'in_progress' },
                { value: 'done', key: 'done' }
            ].forEach(function (st) {
                const isSelected = (task.status || 'pending') === st.value;
                const optInfo = statusMap[st.key];
                const optBtn = document.createElement('button');
                optBtn.type = 'button';
                optBtn.setAttribute('role', 'option');
                optBtn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                optBtn.dataset.taskId = String(task.id);
                optBtn.dataset.status = st.value;
                optBtn.className = 'js-set-task-status flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors ' +
                    (isSelected
                        ? 'bg-emerald-500/10 font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-300 dark:hover:bg-[#2b2b2b] dark:hover:text-white');

                const leftSpan = document.createElement('span');
                leftSpan.className = 'flex items-center gap-2';
                const dot = document.createElement('span');
                dot.className = 'inline-block h-2 w-2 rounded-full ' + optInfo.dot.replace('animate-pulse', '');
                dot.setAttribute('aria-hidden', 'true');
                const label = document.createElement('span');
                label.textContent = optInfo.label;
                leftSpan.appendChild(dot);
                leftSpan.appendChild(label);
                optBtn.appendChild(leftSpan);

                if (isSelected) {
                    optBtn.insertAdjacentHTML('beforeend', '<svg class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>');
                }

                statusMenu.appendChild(optBtn);
            });

            statusWrap.appendChild(statusBtn);
            statusWrap.appendChild(statusMenu);

            // Dropdown Custom de Responsable (SaaS style)
            const assigneeWrap = document.createElement('div');
            assigneeWrap.className = 'relative inline-block text-left js-assignee-dropdown';

            const optionsList = participants.includes(task.assignee)
                ? participants
                : (task.assignee ? [task.assignee].concat(participants) : participants);

            const currentAssigneeName = task.assignee || 'Sin asignar';

            const assigneeBtn = document.createElement('button');
            assigneeBtn.type = 'button';
            assigneeBtn.className = 'js-assignee-dropdown-trigger inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-xs transition-all duration-200 hover:border-slate-300 dark:border-[#383838] dark:bg-[#252525] dark:text-zinc-200 dark:hover:border-[#484848] dark:hover:bg-[#2b2b2b] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40';
            assigneeBtn.dataset.taskId = String(task.id);
            assigneeBtn.setAttribute('aria-haspopup', 'listbox');
            assigneeBtn.setAttribute('aria-expanded', 'false');
            assigneeBtn.setAttribute('aria-label', (i18n.decisionSpeakerLabel || 'Responsable') + ': ' + currentAssigneeName);

            assigneeBtn.insertAdjacentHTML('beforeend', '<svg class="h-3.5 w-3.5 text-slate-400 dark:text-zinc-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>');
            const assigneeNameSpan = document.createElement('span');
            assigneeNameSpan.className = 'max-w-[130px] truncate';
            assigneeNameSpan.textContent = currentAssigneeName;
            assigneeBtn.appendChild(assigneeNameSpan);
            assigneeBtn.insertAdjacentHTML('beforeend', '<svg class="h-3 w-3 opacity-60 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>');

            const assigneeMenu = document.createElement('div');
            assigneeMenu.className = 'js-assignee-menu dropdown-menu-popover hidden absolute left-0 top-full mt-1.5 z-40 max-h-56 w-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-2xl dark:border-[#383838] dark:bg-[#202020]';
            assigneeMenu.setAttribute('role', 'listbox');

            optionsList.forEach(function (person) {
                if (!person) return;
                const isSelected = person === task.assignee;
                const optBtn = document.createElement('button');
                optBtn.type = 'button';
                optBtn.setAttribute('role', 'option');
                optBtn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                optBtn.dataset.taskId = String(task.id);
                optBtn.dataset.assignee = person;
                optBtn.className = 'js-set-task-assignee flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors ' +
                    (isSelected
                        ? 'bg-emerald-500/10 font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-300 dark:hover:bg-[#2b2b2b] dark:hover:text-white');

                const personSpan = document.createElement('span');
                personSpan.className = 'truncate';
                personSpan.textContent = person;
                optBtn.appendChild(personSpan);

                if (isSelected) {
                    optBtn.insertAdjacentHTML('beforeend', '<svg class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400 shrink-0 ml-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>');
                }

                assigneeMenu.appendChild(optBtn);
            });

            assigneeWrap.appendChild(assigneeBtn);
            assigneeWrap.appendChild(assigneeMenu);

            leftGroup.appendChild(statusWrap);
            leftGroup.appendChild(assigneeWrap);

            const rightGroup = document.createElement('div');
            rightGroup.className = 'flex items-center gap-1.5';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'js-edit-task-summary inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:border-[#383838] dark:bg-[#252525] dark:text-zinc-300 dark:hover:bg-[#313131] dark:hover:text-white';
            editBtn.dataset.taskId = String(task.id);
            editBtn.title = i18n.taskEditBtn || 'Editar tarea';
            editBtn.setAttribute('aria-label', i18n.taskEditBtn || 'Editar tarea');
            editBtn.innerHTML = '<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';

            const jumpBtn = document.createElement('button');
            jumpBtn.type = 'button';
            jumpBtn.className = 'js-jump-minute inline-flex items-center gap-1 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 font-mono text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-500/20 dark:text-emerald-300';
            jumpBtn.dataset.timestamp = task.timestamp_label || '00:00';
            jumpBtn.dataset.entryId = String(task.entry_id || '');
            jumpBtn.title = i18n.taskJumpToMinute || 'Ir al minuto';
            jumpBtn.innerHTML = '<span>' + (task.timestamp_label || '00:00') + '</span>' +
                '<svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="7 17 17 7 17 17"/><polyline points="7 7 17 7"/></svg>';

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'js-delete-task inline-flex items-center justify-center rounded-lg border border-red-500/30 bg-red-500/10 p-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-500/20 dark:text-red-300';
            delBtn.dataset.taskId = String(task.id);
            delBtn.title = i18n.taskDeleteBtn || 'Eliminar tarea';
            delBtn.setAttribute('aria-label', i18n.taskDeleteBtn || 'Eliminar tarea');
            delBtn.innerHTML = '<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

            rightGroup.appendChild(editBtn);
            rightGroup.appendChild(jumpBtn);
            rightGroup.appendChild(delBtn);

            controls.appendChild(leftGroup);
            controls.appendChild(rightGroup);

            li.appendChild(topRow);
            li.appendChild(controls);
            frag.appendChild(li);
        });

        dom.tasksList.appendChild(frag);
    }

    /**
     * Renderiza las tarjetas del Buscador Semántico de Decisiones Técnicas (Módulo 2).
     */
    function renderDecisions(decisionsArray, expandedTerms) {
        if (!dom.decisionsList) return;
        dom.decisionsList.textContent = '';

        const list = Array.isArray(decisionsArray) ? decisionsArray : [];
        if (dom.tabCountDecisions && state.decisionScope === 'current' && !state.decisionSearchQuery) {
            dom.tabCountDecisions.textContent = String(state.decisions.length);
        } else if (dom.tabCountDecisions) {
            dom.tabCountDecisions.textContent = String(list.length);
        }

        if (dom.expandedTermsBar) {
            if (Array.isArray(expandedTerms) && expandedTerms.length > 0) {
                dom.expandedTermsBar.textContent = (i18n.expandedTermsPrefix || 'Búsqueda expandida con') + ': ' + expandedTerms.join(', ');
                dom.expandedTermsBar.hidden = false;
            } else {
                dom.expandedTermsBar.hidden = true;
            }
        }

        const filtered = list.filter(function (dec) {
            if (state.decisionCategoryFilter && dec.category !== state.decisionCategoryFilter) {
                return false;
            }
            if (state.selectedSpeaker !== 'all' && dec.speaker !== state.selectedSpeaker) {
                return false;
            }
            return true;
        });

        if (filtered.length === 0) {
            const emptyLi = document.createElement('li');
            emptyLi.className = 'rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500 dark:border-[#313131] dark:bg-[#181818] dark:text-zinc-400';
            emptyLi.textContent = i18n.emptyDecisions || '';
            dom.decisionsList.appendChild(emptyLi);
            return;
        }

        const catLabels = {
            decision: i18n.catDecision || 'Decisión técnica',
            blocker: i18n.catBlocker || 'Bloqueante o limitación',
            architecture: i18n.catArchitecture || 'Flujo o arquitectura'
        };

        const catStyles = {
            decision: 'rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:text-sky-300',
            blocker: 'rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300',
            architecture: 'rounded-md border border-violet-500/30 bg-violet-500/10 px-2 py-0.5 text-xs font-semibold text-violet-700 dark:text-violet-300'
        };

        const frag = document.createDocumentFragment();
        filtered.forEach(function (dec) {
            const li = document.createElement('li');
            li.className = 'decision-row rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-[#313131] dark:bg-[#181818]';
            li.dataset.decisionId = String(dec.id || '');

            const top = document.createElement('div');
            top.className = 'flex flex-wrap items-center justify-between gap-2';

            const catKey = dec.category || 'decision';
            const badge = document.createElement('span');
            badge.className = catStyles[catKey] || catStyles.decision;
            badge.textContent = catLabels[catKey] || catLabels.decision;

            const actionsRight = document.createElement('div');
            actionsRight.className = 'flex items-center gap-1.5';

            const isOtherMeeting = state.meeting && dec.meeting_id && Number(dec.meeting_id) !== Number(state.meeting.id);
            const jumpBtn = document.createElement('button');
            jumpBtn.type = 'button';
            jumpBtn.className = 'js-jump-minute rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 font-mono text-xs font-semibold text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300';
            jumpBtn.dataset.timestamp = dec.timestamp_label || '00:00';
            jumpBtn.dataset.entryId = String(dec.entry_id || '');
            jumpBtn.dataset.meetingId = String(dec.meeting_id || '');
            jumpBtn.textContent = (isOtherMeeting ? (i18n.decisionSwitchAndJump || 'Abrir') : (i18n.decisionViewInContext || 'Ver')) + ' (' + (dec.timestamp_label || '00:00') + ') ↗';

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'js-delete-decision rounded-lg border border-red-500/30 bg-red-500/10 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-500/20 dark:text-red-300';
            delBtn.dataset.decisionId = String(dec.id || '');
            delBtn.title = i18n.decisionDeleteBtn || '';
            delBtn.setAttribute('aria-label', i18n.decisionDeleteBtn || '');
            delBtn.textContent = '×';

            actionsRight.appendChild(jumpBtn);
            actionsRight.appendChild(delBtn);

            top.appendChild(badge);
            top.appendChild(actionsRight);

            const titleH3 = document.createElement('h3');
            titleH3.className = 'mt-2.5 text-sm font-bold text-slate-900 dark:text-white';
            titleH3.textContent = dec.title || '';

            const rationaleP = document.createElement('p');
            rationaleP.className = 'mt-1 text-xs leading-relaxed text-slate-600 dark:text-zinc-300';
            rationaleP.textContent = dec.rationale || '';

            li.appendChild(top);
            li.appendChild(titleH3);
            li.appendChild(rationaleP);

            if (dec.entry_context) {
                const quoteP = document.createElement('p');
                quoteP.className = 'mt-2 rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-[11px] text-slate-600 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300';
                quoteP.textContent = dec.entry_context;
                li.appendChild(quoteP);
            }

            const metaDiv = document.createElement('div');
            metaDiv.className = 'mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-zinc-400';

            const speakerSpan = document.createElement('span');
            speakerSpan.textContent = (i18n.decisionSpeakerLabel || 'Autor') + ': ' + (dec.speaker || '');
            metaDiv.appendChild(speakerSpan);

            if (dec.meeting_title) {
                const originSpan = document.createElement('span');
                originSpan.className = 'font-medium text-slate-700 dark:text-zinc-300';
                originSpan.textContent = (i18n.decisionMeetingOriginLabel || 'Sesión') + ': ' + dec.meeting_title;
                metaDiv.appendChild(originSpan);
            }

            if (dec.semantic_tags) {
                const tagsSpan = document.createElement('span');
                tagsSpan.className = 'rounded bg-slate-200/70 px-1.5 py-0.5 font-mono text-[11px] text-slate-700 dark:bg-[#272727] dark:text-zinc-300';
                tagsSpan.textContent = dec.semantic_tags;
                metaDiv.appendChild(tagsSpan);
            }

            li.appendChild(metaDiv);
            frag.appendChild(li);
        });

        dom.decisionsList.appendChild(frag);
    }

    /**
     * Renderiza el diálogo cronológico de la transcripción con acciones rápidas (+ Tarea / + Decisión) en 1 clic.
     */
    function renderTranscript() {
        if (!dom.transcriptList) return;
        dom.transcriptList.textContent = '';
        const textFilter = state.transcriptFilterText.trim().toLowerCase();

        const filtered = state.entries.filter(function (entry) {
            if (state.selectedSpeaker !== 'all' && entry.speaker !== state.selectedSpeaker) {
                return false;
            }
            if (textFilter !== '') {
                const hay = ((entry.speaker || '') + ' ' + (entry.content || '') + ' ' + (entry.timestamp_label || '')).toLowerCase();
                if (!hay.includes(textFilter)) return false;
            }
            return true;
        });

        if (dom.viewerCountNum) {
            dom.viewerCountNum.textContent = filtered.length + ' / ' + state.entries.length;
        }

        if (filtered.length === 0) {
            const emptyLi = document.createElement('li');
            emptyLi.className = 'rounded-xl border border-slate-200 bg-slate-50 p-5 text-center text-xs text-slate-500 dark:border-[#313131] dark:bg-[#181818] dark:text-zinc-400';
            emptyLi.textContent = state.entries.length === 0
                ? (i18n.emptyMeetingState || '')
                : (i18n.viewerEmptyFilter || '');
            dom.transcriptList.appendChild(emptyLi);
            return;
        }

        const frag = document.createDocumentFragment();
        filtered.forEach(function (entry) {
            const li = document.createElement('li');
            li.className = 'transcript-entry transcript-line group rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#313131] dark:bg-[#181818]';
            li.dataset.entryId = String(entry.id || '');
            li.dataset.timestamp = entry.timestamp_label || '00:00';

            const head = document.createElement('div');
            head.className = 'flex items-center justify-between gap-2';

            const leftMeta = document.createElement('div');
            leftMeta.className = 'flex items-center gap-2 min-w-0';

            const timeSpan = document.createElement('span');
            timeSpan.className = 'rounded bg-slate-200/80 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-700 dark:bg-[#272727] dark:text-emerald-400';
            timeSpan.textContent = entry.timestamp_label || '00:00';

            const speakerSpan = document.createElement('span');
            speakerSpan.className = 'truncate text-xs font-bold text-slate-900 dark:text-white';
            speakerSpan.textContent = entry.speaker || '';

            leftMeta.appendChild(timeSpan);
            leftMeta.appendChild(speakerSpan);

            // Botones 1 clic: + Tarea / + Decisión
            const quickActions = document.createElement('div');
            quickActions.className = 'flex items-center gap-1 opacity-0 transition-opacity duration-300 group-hover:opacity-100 focus-within:opacity-100';

            const btnQuickTask = document.createElement('button');
            btnQuickTask.type = 'button';
            btnQuickTask.className = 'js-quick-task rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-slate-700 hover:border-emerald-500 hover:text-emerald-600 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200 dark:hover:text-emerald-400';
            btnQuickTask.dataset.entryId = String(entry.id || '');
            btnQuickTask.title = i18n.viewerQuickTaskTitle || '';
            btnQuickTask.textContent = i18n.viewerQuickTaskBtn || '+ Tarea';

            const btnQuickDecision = document.createElement('button');
            btnQuickDecision.type = 'button';
            btnQuickDecision.className = 'js-quick-decision rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-slate-700 hover:border-sky-500 hover:text-sky-600 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-200 dark:hover:text-sky-400';
            btnQuickDecision.dataset.entryId = String(entry.id || '');
            btnQuickDecision.title = i18n.viewerQuickDecisionTitle || '';
            btnQuickDecision.textContent = i18n.viewerQuickDecisionBtn || '+ Decisión';

            quickActions.appendChild(btnQuickTask);
            quickActions.appendChild(btnQuickDecision);

            head.appendChild(leftMeta);
            head.appendChild(quickActions);

            const contentP = document.createElement('p');
            contentP.className = 'mt-1.5 text-xs leading-relaxed text-slate-700 dark:text-zinc-300';
            contentP.textContent = entry.content || '';

            li.appendChild(head);
            li.appendChild(contentP);
            frag.appendChild(li);
        });

        dom.transcriptList.appendChild(frag);
    }

    /**
     * Desplaza el visor de transcripción hasta la intervención exacta (cambiando de reunión si es necesario) y la resalta por 2.2s.
     */
    async function jumpToTranscriptLine(entryId, timestampLabel, targetMeetingId) {
        if (targetMeetingId && state.meeting && Number(targetMeetingId) !== Number(state.meeting.id)) {
            await loadMeetingById(targetMeetingId);
        }

        // En móvil, mostrar el panel visor si está oculto
        if (dom.viewerPanel && window.innerWidth < 1024) {
            dom.viewerPanel.classList.remove('hidden');
            dom.viewerPanel.classList.add('flex');
        }

        if (state.selectedSpeaker !== 'all' || state.transcriptFilterText !== '') {
            state.selectedSpeaker = 'all';
            state.transcriptFilterText = '';
            if (dom.transcriptFilterInput) dom.transcriptFilterInput.value = '';
            renderMetadataAndSpeakers();
            renderTranscript();
        }

        if (!dom.transcriptList) return;

        const prevHighlighted = dom.transcriptList.querySelectorAll('.is-highlighted');
        prevHighlighted.forEach(function (el) {
            el.classList.remove('is-highlighted');
        });

        let target = null;
        if (entryId) {
            target = dom.transcriptList.querySelector('[data-entry-id="' + CSS.escape(String(entryId)) + '"]');
        }
        if (!target && timestampLabel) {
            target = dom.transcriptList.querySelector('[data-timestamp="' + CSS.escape(String(timestampLabel)) + '"]');
        }

        if (target) {
            target.classList.add('is-highlighted');
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });

            if (state.highlightTimer) clearTimeout(state.highlightTimer);
            state.highlightTimer = setTimeout(function () {
                target.classList.remove('is-highlighted');
            }, 2200);
        }
    }

    async function loadMeetingById(meetingId) {
        if (!meetingId) return;
        const response = await fetch(baseUrl + '/api/meetings/' + encodeURIComponent(String(meetingId)), {
            headers: buildHeaders(false)
        });
        const data = await response.json();
        if (response.ok && data.status === 'ok') {
            hydrateMeetingData(data);
        }
    }

    async function refreshMeetingsList(selectedId) {
        const response = await fetch(baseUrl + '/api/meetings', {
            headers: buildHeaders(false)
        });
        const data = await response.json();
        if (!response.ok || !Array.isArray(data.meetings)) return;

        state.meetings = data.meetings;

        if (dom.meetingSelect) {
            dom.meetingSelect.textContent = '';
            data.meetings.forEach(function (m) {
                const opt = document.createElement('option');
                opt.value = String(m.id);
                opt.textContent = m.title || m.titulo || '';
                if (selectedId && Number(m.id) === Number(selectedId)) {
                    opt.selected = true;
                }
                dom.meetingSelect.appendChild(opt);
            });
        }

        if (selectedId) {
            renderSidebarMeetings();
            return;
        }

        if (data.meetings.length > 0) {
            await loadMeetingById(data.meetings[0].id);
        } else {
            hydrateMeetingData(null);
        }
    }

    /**
     * Ejecuta búsqueda semántica contra `GET /api/decisions/search` con AbortController.
     */
    async function executeSemanticSearch() {
        if (state.searchAbortController) {
            state.searchAbortController.abort();
        }
        state.searchAbortController = new AbortController();

        const meetingIdParam = (state.decisionScope === 'current' && state.meeting && state.meeting.id)
            ? String(state.meeting.id)
            : '0';

        const params = new URLSearchParams({
            q: state.decisionSearchQuery,
            category: state.decisionCategoryFilter,
            speaker: state.selectedSpeaker === 'all' ? '' : state.selectedSpeaker,
            meeting_id: meetingIdParam
        });

        try {
            const response = await fetch(baseUrl + '/api/decisions/search?' + params.toString(), {
                method: 'GET',
                headers: buildHeaders(false),
                signal: state.searchAbortController.signal
            });
            const data = await response.json();
            if (response.ok && data.status === 'ok') {
                renderDecisions(data.results || [], data.expanded_terms || []);
            }
        } catch (err) {
            if (err && err.name === 'AbortError') {
                return;
            }
        }
    }

    // =========================================================================
    // EVENT LISTENERS Y DELEGACIÓN
    // =========================================================================

    // 1. Tema Oscuro / Claro y Colapso de Sidebar
    let themeRotation = 0;
    if (dom.btnThemeToggle) {
        dom.btnThemeToggle.addEventListener('click', function () {
            const isDark = document.documentElement.classList.toggle('dark');
            try {
                localStorage.setItem('app_theme', isDark ? 'dark' : 'light');
            } catch (e) {}
            themeRotation += 180;
            dom.btnThemeToggle.style.transform = 'rotate(' + themeRotation + 'deg)';
        });
    }

    function setSidebarCollapsed(collapsed) {
        if (!dom.sidebar) return;
        dom.sidebar.classList.toggle('hidden', collapsed);
        dom.sidebar.classList.toggle('lg:hidden', collapsed);
        if (dom.btnSidebarExpand) {
            dom.btnSidebarExpand.classList.toggle('lg:hidden', !collapsed);
        }
        try {
            localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
        } catch (e) {}
    }

    if (dom.btnSidebarCollapse) {
        dom.btnSidebarCollapse.addEventListener('click', function () {
            setSidebarCollapsed(true);
        });
    }
    if (dom.btnSidebarExpand) {
        dom.btnSidebarExpand.addEventListener('click', function () {
            setSidebarCollapsed(false);
        });
    }

    if (dom.btnToggleViewerMobile && dom.viewerPanel) {
        dom.btnToggleViewerMobile.addEventListener('click', function () {
            dom.viewerPanel.classList.toggle('hidden');
            dom.viewerPanel.classList.toggle('flex');
        });
    }
    if (dom.btnCloseViewerMobile && dom.viewerPanel) {
        dom.btnCloseViewerMobile.addEventListener('click', function () {
            dom.viewerPanel.classList.add('hidden');
            dom.viewerPanel.classList.remove('flex');
        });
    }

    // 2. Pestañas Módulo 1 / Módulo 2 y Atajo Global Ctrl + K
    if (dom.tabBtnMinutes) {
        dom.tabBtnMinutes.addEventListener('click', function () {
            switchModuleTab('minutes');
        });
    }
    if (dom.tabBtnDecisions) {
        dom.tabBtnDecisions.addEventListener('click', function () {
            switchModuleTab('decisions');
        });
    }
    if (dom.btnShortcutCtrlK) {
        dom.btnShortcutCtrlK.addEventListener('click', function () {
            switchModuleTab('decisions');
            if (dom.decisionSearchInput) dom.decisionSearchInput.focus();
        });
    }

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            switchModuleTab('decisions');
            if (dom.decisionSearchInput) {
                dom.decisionSearchInput.focus();
                dom.decisionSearchInput.select();
            }
        }
    });

    // 3. Filtro y Selección de Reuniones en el Sidebar
    if (dom.sidebarMeetingFilter) {
        dom.sidebarMeetingFilter.addEventListener('input', function () {
            state.sidebarSearchText = dom.sidebarMeetingFilter.value;
            renderSidebarMeetings();
        });
    }

    if (dom.meetingsList) {
        dom.meetingsList.addEventListener('click', async function (e) {
            const delBtn = e.target.closest('[data-delete-meeting-id]');
            if (delBtn) {
                state.pendingDeleteMeetingId = Number(delBtn.dataset.deleteMeetingId);
                if (dom.confirmDeleteDialog && typeof dom.confirmDeleteDialog.showModal === 'function') {
                    dom.confirmDeleteDialog.showModal();
                }
                return;
            }

            const selectBtn = e.target.closest('[data-select-meeting-id]');
            if (selectBtn) {
                await loadMeetingById(selectBtn.dataset.selectMeetingId);
            }
        });
    }

    if (dom.meetingSelect) {
        dom.meetingSelect.addEventListener('change', async function () {
            await loadMeetingById(dom.meetingSelect.value);
        });
    }

    // 4. Subida de Archivo .txt (Modal <dialog> Drag and Drop) y Muestra Real
    if (dom.btnUploadTxt && dom.uploadDialog) {
        dom.btnUploadTxt.addEventListener('click', function () {
            state.selectedUploadFile = null;
            if (dom.fileInput) dom.fileInput.value = '';
            if (dom.uploadCustomTitle) dom.uploadCustomTitle.value = '';
            if (dom.uploadSelectedFilename) {
                dom.uploadSelectedFilename.textContent = i18n.uploadDropzonePrompt || '';
            }
            if (typeof dom.uploadDialog.showModal === 'function') {
                dom.uploadDialog.showModal();
            } else if (dom.fileInput) {
                dom.fileInput.click();
            }
        });
    }

    if (dom.uploadDropzone && dom.fileInput) {
        dom.uploadDropzone.addEventListener('click', function () {
            dom.fileInput.click();
        });

        dom.uploadDropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dom.uploadDropzone.classList.add('border-emerald-500');
        });

        dom.uploadDropzone.addEventListener('dragleave', function () {
            dom.uploadDropzone.classList.remove('border-emerald-500');
        });

        dom.uploadDropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dom.uploadDropzone.classList.remove('border-emerald-500');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                state.selectedUploadFile = e.dataTransfer.files[0];
                if (dom.uploadSelectedFilename) {
                    dom.uploadSelectedFilename.textContent = state.selectedUploadFile.name;
                }
            }
        });

        dom.fileInput.addEventListener('change', function () {
            if (dom.fileInput.files && dom.fileInput.files.length > 0) {
                state.selectedUploadFile = dom.fileInput.files[0];
                if (dom.uploadSelectedFilename) {
                    dom.uploadSelectedFilename.textContent = state.selectedUploadFile.name;
                }
            }
        });
    }

    if (dom.formUploadModal) {
        dom.formUploadModal.addEventListener('submit', async function (e) {
            e.preventDefault();
            const file = state.selectedUploadFile || (dom.fileInput && dom.fileInput.files ? dom.fileInput.files[0] : null);
            if (!file) {
                showBanner(i18n.uploadErrorInvalidFile || 'Selecciona un archivo .txt.');
                return;
            }

            if (!file.name.toLowerCase().endsWith('.txt') || file.size > 5 * 1024 * 1024) {
                showBanner(i18n.uploadErrorInvalidFile || 'Archivo .txt inválido.');
                return;
            }

            const formData = new FormData();
            formData.append('transcript_file', file);
            if (dom.uploadCustomTitle && dom.uploadCustomTitle.value.trim() !== '') {
                formData.append('title', dom.uploadCustomTitle.value.trim());
            }

            if (dom.btnUploadSubmit) dom.btnUploadSubmit.disabled = true;
            try {
                const response = await fetch(baseUrl + '/api/meetings/upload', {
                    method: 'POST',
                    headers: buildHeaders(false),
                    body: formData
                });
                const result = await response.json();
                if (response.ok && result.status === 'ok' && result.data) {
                    if (dom.uploadDialog && typeof dom.uploadDialog.close === 'function') {
                        dom.uploadDialog.close();
                    }
                    showBanner(result.message || i18n.uploadSuccessMsg || '');
                    hydrateMeetingData(result.data);
                    await refreshMeetingsList(result.meeting_id);
                } else {
                    showBanner(result.message || i18n.uploadErrorInvalidFile || '');
                }
            } finally {
                if (dom.btnUploadSubmit) dom.btnUploadSubmit.disabled = false;
            }
        });
    }



    // 5. Eliminación de Reunión con Modal <dialog>
    if (dom.btnDeleteMeeting) {
        dom.btnDeleteMeeting.addEventListener('click', function () {
            if (!state.meeting || !state.meeting.id) return;
            state.pendingDeleteMeetingId = Number(state.meeting.id);
            if (dom.confirmDeleteDialog && typeof dom.confirmDeleteDialog.showModal === 'function') {
                dom.confirmDeleteDialog.showModal();
            }
        });
    }

    if (dom.btnConfirmDeleteMeeting) {
        dom.btnConfirmDeleteMeeting.addEventListener('click', async function () {
            const idToDelete = state.pendingDeleteMeetingId || (state.meeting ? state.meeting.id : null);
            if (!idToDelete) return;

            dom.btnConfirmDeleteMeeting.disabled = true;
            try {
                const response = await fetch(baseUrl + '/api/meetings/' + encodeURIComponent(String(idToDelete)), {
                    method: 'DELETE',
                    headers: buildHeaders(true)
                });
                if (response.ok) {
                    if (dom.confirmDeleteDialog && typeof dom.confirmDeleteDialog.close === 'function') {
                        dom.confirmDeleteDialog.close();
                    }
                    state.pendingDeleteMeetingId = null;
                    await refreshMeetingsList(null);
                }
            } finally {
                dom.btnConfirmDeleteMeeting.disabled = false;
            }
        });
    }

    document.querySelectorAll('[data-close-dialog]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-close-dialog');
            const dlg = id ? document.getElementById(id) : null;
            if (dlg && typeof dlg.close === 'function') {
                dlg.close();
            }
        });
    });

    // 6. Edición Inline del Título de la Reunión (`PATCH /api/meetings/{id}`)
    function openMeetingTitleEditor() {
        if (!state.meeting || !dom.meetingTitleDisplay || !dom.meetingTitleInput) return;
        dom.meetingTitleInput.value = state.meeting.title || state.meeting.titulo || '';
        dom.meetingTitleDisplay.hidden = true;
        dom.meetingTitleInput.hidden = false;
        dom.meetingTitleInput.focus();
        dom.meetingTitleInput.select();
    }

    async function saveMeetingTitleEditor() {
        if (!state.meeting || !dom.meetingTitleDisplay || !dom.meetingTitleInput || dom.meetingTitleInput.hidden) return;
        const newTitle = dom.meetingTitleInput.value.trim();
        dom.meetingTitleInput.hidden = true;
        dom.meetingTitleDisplay.hidden = false;

        if (!newTitle || newTitle === (state.meeting.title || state.meeting.titulo)) {
            return;
        }

        const response = await fetch(baseUrl + '/api/meetings/' + encodeURIComponent(String(state.meeting.id)), {
            method: 'PATCH',
            headers: buildHeaders(true),
            body: JSON.stringify({ title: newTitle })
        });
        const data = await response.json();
        if (response.ok && data.status === 'ok' && data.meeting) {
            state.meeting.title = data.meeting.title;
            state.meeting.titulo = data.meeting.title;
            dom.meetingTitleDisplay.textContent = data.meeting.title;
            const inList = state.meetings.find(function (m) { return Number(m.id) === Number(state.meeting.id); });
            if (inList) {
                inList.title = data.meeting.title;
                inList.titulo = data.meeting.title;
            }
            renderSidebarMeetings();
            showBanner(data.message || i18n.meetingUpdatedMsg || '');
        }
    }

    if (dom.btnEditMeetingTitle) {
        dom.btnEditMeetingTitle.addEventListener('click', openMeetingTitleEditor);
    }
    if (dom.meetingTitleDisplay) {
        dom.meetingTitleDisplay.addEventListener('click', openMeetingTitleEditor);
    }
    if (dom.meetingTitleInput) {
        dom.meetingTitleInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                dom.meetingTitleInput.blur();
            } else if (e.key === 'Escape') {
                dom.meetingTitleInput.hidden = true;
                dom.meetingTitleDisplay.hidden = false;
            }
        });
        dom.meetingTitleInput.addEventListener('blur', saveMeetingTitleEditor);
    }

    // 7. Edición Inline de la Minuta Ejecutiva (`PATCH /api/meetings/{id}`)
    if (dom.btnEditSummary && dom.summaryEditorWrap && dom.executiveSummaryText && dom.executiveSummaryInput) {
        dom.btnEditSummary.addEventListener('click', function () {
            if (!state.meeting) return;
            dom.executiveSummaryInput.value = state.meeting.executive_summary || '';
            dom.executiveSummaryText.hidden = true;
            dom.summaryEditorWrap.hidden = false;
            dom.executiveSummaryInput.focus();
        });
    }

    if (dom.btnCancelSummary && dom.summaryEditorWrap && dom.executiveSummaryText) {
        dom.btnCancelSummary.addEventListener('click', function () {
            dom.summaryEditorWrap.hidden = true;
            dom.executiveSummaryText.hidden = false;
        });
    }

    if (dom.btnSaveSummary && dom.executiveSummaryInput) {
        dom.btnSaveSummary.addEventListener('click', async function () {
            if (!state.meeting) return;
            const newSummary = dom.executiveSummaryInput.value.trim();
            if (!newSummary) return;

            dom.btnSaveSummary.disabled = true;
            try {
                const response = await fetch(baseUrl + '/api/meetings/' + encodeURIComponent(String(state.meeting.id)), {
                    method: 'PATCH',
                    headers: buildHeaders(true),
                    body: JSON.stringify({ executive_summary: newSummary })
                });
                const data = await response.json();
                if (response.ok && data.status === 'ok' && data.meeting) {
                    state.meeting.executive_summary = data.meeting.executive_summary;
                    dom.executiveSummaryText.textContent = data.meeting.executive_summary;
                    dom.summaryEditorWrap.hidden = true;
                    dom.executiveSummaryText.hidden = false;
                    showBanner(data.message || i18n.meetingUpdatedMsg || '');
                }
            } finally {
                dom.btnSaveSummary.disabled = false;
            }
        });
    }

    // 8. Chips de Participantes y Limpieza de Filtro
    if (dom.speakerChips) {
        dom.speakerChips.addEventListener('click', function (e) {
            const btn = e.target.closest('.speaker-chip');
            if (!btn) return;
            state.selectedSpeaker = btn.dataset.speaker || 'all';
            renderMetadataAndSpeakers();
            renderTasks();
            renderTranscript();
            executeSemanticSearch();
        });
    }

    if (dom.btnClearSpeakerFilter) {
        dom.btnClearSpeakerFilter.addEventListener('click', function () {
            state.selectedSpeaker = 'all';
            renderMetadataAndSpeakers();
            renderTasks();
            renderTranscript();
            executeSemanticSearch();
        });
    }

    // 9. Módulo 1: Creación, Filtrado, Edición Inline y Delegación de Tareas
    if (dom.formCreateTask) {
        dom.formCreateTask.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!state.meeting) return;
            const summary = dom.newTaskSummary.value.trim();
            if (!summary) return;

            const response = await fetch(baseUrl + '/api/tasks', {
                method: 'POST',
                headers: buildHeaders(true),
                body: JSON.stringify({
                    meeting_id: state.meeting.id,
                    summary: summary,
                    assignee: dom.newTaskAssignee.value || (i18n.taskDefaultAssignee || 'Equipo'),
                    timestamp_label: dom.newTaskTimestamp.value || '00:00'
                })
            });
            const data = await response.json();
            if (response.ok && data.task) {
                state.tasks.push(data.task);
                dom.newTaskSummary.value = '';
                renderTasks();
                showBanner(i18n.taskCreatedMsg || '');
            }
        });
    }

    if (dom.taskStatusFilters) {
        dom.taskStatusFilters.addEventListener('click', function (e) {
            const pill = e.target.closest('[data-status-filter]');
            if (!pill) return;
            state.taskStatusFilter = pill.dataset.statusFilter || 'all';
            dom.taskStatusFilters.querySelectorAll('[data-status-filter]').forEach(function (el) {
                const active = el === pill;
                el.setAttribute('aria-pressed', active ? 'true' : 'false');
                el.className = active
                    ? 'rounded-lg border border-slate-200 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white dark:border-[#313131] dark:bg-[#313131] dark:text-white'
                    : 'rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300';
            });
            renderTasks();
        });
    }

    if (dom.tasksList) {
        dom.tasksList.addEventListener('change', async function (e) {
            const target = e.target;

            if (target.classList.contains('js-toggle-task-done')) {
                const taskId = target.dataset.taskId;
                const newStatus = target.checked ? 'done' : 'pending';
                const response = await fetch(baseUrl + '/api/tasks/' + encodeURIComponent(taskId), {
                    method: 'PATCH',
                    headers: buildHeaders(true),
                    body: JSON.stringify({ status: newStatus })
                });
                if (response.ok) {
                    const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                    if (found) found.status = newStatus;
                    renderTasks();
                    showBanner(i18n.taskUpdatedMsg || '');
                }
                return;
            }

            if (target.classList.contains('js-task-status')) {
                const taskId = target.dataset.taskId;
                const newStatus = target.value;
                const response = await fetch(baseUrl + '/api/tasks/' + encodeURIComponent(taskId), {
                    method: 'PATCH',
                    headers: buildHeaders(true),
                    body: JSON.stringify({ status: newStatus })
                });
                if (response.ok) {
                    const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                    if (found) found.status = newStatus;
                    renderTasks();
                    showBanner(i18n.taskUpdatedMsg || '');
                }
                return;
            }

            if (target.classList.contains('js-task-assignee')) {
                const taskId = target.dataset.taskId;
                const newAssignee = target.value;
                const response = await fetch(baseUrl + '/api/tasks/' + encodeURIComponent(taskId), {
                    method: 'PATCH',
                    headers: buildHeaders(true),
                    body: JSON.stringify({ assignee: newAssignee })
                });
                if (response.ok) {
                    const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                    if (found) found.assignee = newAssignee;
                    showBanner(i18n.taskUpdatedMsg || '');
                }
            }
        });

        dom.tasksList.addEventListener('click', async function (e) {
            // Dropdown de Estado: Toggle abrir/cerrar
            const statusTrigger = e.target.closest('.js-status-dropdown-trigger');
            if (statusTrigger) {
                const wrap = statusTrigger.closest('.js-status-dropdown');
                const menu = wrap ? wrap.querySelector('.js-status-menu') : null;
                if (menu) {
                    const willOpen = menu.classList.contains('hidden');
                    closeAllTaskDropdowns();
                    if (willOpen) {
                        menu.classList.remove('hidden');
                        statusTrigger.setAttribute('aria-expanded', 'true');
                    }
                }
                return;
            }

            // Dropdown de Estado: Seleccionar estado
            const statusOpt = e.target.closest('.js-set-task-status');
            if (statusOpt) {
                const taskId = statusOpt.dataset.taskId;
                const newStatus = statusOpt.dataset.status;
                closeAllTaskDropdowns();

                const response = await fetch(baseUrl + '/api/tasks/' + encodeURIComponent(taskId), {
                    method: 'PATCH',
                    headers: buildHeaders(true),
                    body: JSON.stringify({ status: newStatus })
                });
                if (response.ok) {
                    const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                    if (found) found.status = newStatus;
                    renderTasks();
                    showBanner(i18n.taskUpdatedMsg || '');
                }
                return;
            }

            // Dropdown de Responsable: Toggle abrir/cerrar
            const assigneeTrigger = e.target.closest('.js-assignee-dropdown-trigger');
            if (assigneeTrigger) {
                const wrap = assigneeTrigger.closest('.js-assignee-dropdown');
                const menu = wrap ? wrap.querySelector('.js-assignee-menu') : null;
                if (menu) {
                    const willOpen = menu.classList.contains('hidden');
                    closeAllTaskDropdowns();
                    if (willOpen) {
                        menu.classList.remove('hidden');
                        assigneeTrigger.setAttribute('aria-expanded', 'true');
                    }
                }
                return;
            }

            // Dropdown de Responsable: Seleccionar responsable
            const assigneeOpt = e.target.closest('.js-set-task-assignee');
            if (assigneeOpt) {
                const taskId = assigneeOpt.dataset.taskId;
                const newAssignee = assigneeOpt.dataset.assignee;
                closeAllTaskDropdowns();

                const response = await fetch(baseUrl + '/api/tasks/' + encodeURIComponent(taskId), {
                    method: 'PATCH',
                    headers: buildHeaders(true),
                    body: JSON.stringify({ assignee: newAssignee })
                });
                if (response.ok) {
                    const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                    if (found) found.assignee = newAssignee;
                    renderTasks();
                    showBanner(i18n.taskUpdatedMsg || '');
                }
                return;
            }

            const jumpBtn = e.target.closest('.js-jump-minute');
            if (jumpBtn) {
                await jumpToTranscriptLine(jumpBtn.dataset.entryId, jumpBtn.dataset.timestamp, null);
                return;
            }

            const editBtn = e.target.closest('.js-edit-task-summary');
            if (editBtn) {
                const row = editBtn.closest('.task-row');
                if (!row) return;
                const pEl = row.querySelector('.task-summary-text');
                const editWrap = row.querySelector('.js-task-edit-wrap');
                const textarea = row.querySelector('.js-task-summary-textarea');
                if (pEl && editWrap && textarea) {
                    const isEditing = !editWrap.classList.contains('hidden');
                    if (isEditing) {
                        editWrap.classList.add('hidden');
                        pEl.hidden = false;
                    } else {
                        // Cierra otros editores abiertos para mantener la vista limpia
                        dom.tasksList.querySelectorAll('.js-task-edit-wrap:not(.hidden)').forEach(function (w) {
                            w.classList.add('hidden');
                            const otherP = w.parentElement ? w.parentElement.querySelector('.task-summary-text') : null;
                            if (otherP) otherP.hidden = false;
                        });

                        pEl.hidden = true;
                        editWrap.classList.remove('hidden');
                        const taskId = editBtn.dataset.taskId;
                        const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                        if (found) textarea.value = found.summary || '';
                        textarea.focus();
                        textarea.select();
                    }
                }
                return;
            }

            const cancelBtn = e.target.closest('.js-cancel-task-edit');
            if (cancelBtn) {
                const row = cancelBtn.closest('.task-row');
                if (!row) return;
                const pEl = row.querySelector('.task-summary-text');
                const editWrap = row.querySelector('.js-task-edit-wrap');
                const textarea = row.querySelector('.js-task-summary-textarea');
                const taskId = cancelBtn.dataset.taskId;
                const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                if (textarea && found) textarea.value = found.summary || '';
                if (editWrap) editWrap.classList.add('hidden');
                if (pEl) pEl.hidden = false;
                return;
            }

            const saveBtn = e.target.closest('.js-save-task-edit');
            if (saveBtn) {
                const row = saveBtn.closest('.task-row');
                if (!row) return;
                const taskId = saveBtn.dataset.taskId;
                const textarea = row.querySelector('.js-task-summary-textarea');
                if (textarea) {
                    await saveTaskSummary(taskId, textarea.value.trim(), row);
                }
                return;
            }

            const delBtn = e.target.closest('.js-delete-task');
            if (delBtn) {
                const taskId = delBtn.dataset.taskId;
                const response = await fetch(baseUrl + '/api/tasks/' + encodeURIComponent(taskId), {
                    method: 'DELETE',
                    headers: buildHeaders(true)
                });
                if (response.ok) {
                    state.tasks = state.tasks.filter(function (t) { return String(t.id) !== String(taskId); });
                    renderTasks();
                    showBanner(i18n.taskDeletedMsg || '');
                }
            }
        });

        dom.tasksList.addEventListener('keydown', async function (e) {
            const target = e.target;
            if (!target.classList.contains('js-task-summary-textarea')) return;

            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                const row = target.closest('.task-row');
                const taskId = target.dataset.taskId;
                if (row && taskId) {
                    await saveTaskSummary(taskId, target.value.trim(), row);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                const row = target.closest('.task-row');
                const pEl = row ? row.querySelector('.task-summary-text') : null;
                const editWrap = row ? row.querySelector('.js-task-edit-wrap') : null;
                const taskId = target.dataset.taskId;
                const found = state.tasks.find(function (t) { return String(t.id) === String(taskId); });
                if (target && found) target.value = found.summary || '';
                if (editWrap) editWrap.classList.add('hidden');
                if (pEl) pEl.hidden = false;
            }
        });

        // Cierre de dropdowns flotantes al hacer clic fuera o presionar Escape
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.js-status-dropdown') && !e.target.closest('.js-assignee-dropdown')) {
                closeAllTaskDropdowns();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllTaskDropdowns();
            }
        });
    }

    // 10. Módulo 2: Buscador Semántico FTS5, Alcance, Chips, Alta Manual y Borrado
    if (dom.decisionSearchInput) {
        dom.decisionSearchInput.addEventListener('input', function () {
            state.decisionSearchQuery = dom.decisionSearchInput.value;
            if (state.debounceTimer) {
                clearTimeout(state.debounceTimer);
            }
            state.debounceTimer = setTimeout(function () {
                executeSemanticSearch();
            }, 250);
        });
    }

    if (dom.decisionScopeToggle) {
        dom.decisionScopeToggle.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-scope]');
            if (!btn) return;
            state.decisionScope = btn.dataset.scope === 'all' ? 'all' : 'current';
            dom.decisionScopeToggle.querySelectorAll('[data-scope]').forEach(function (el) {
                const active = el === btn;
                el.setAttribute('aria-pressed', active ? 'true' : 'false');
                el.className = active
                    ? 'rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-900 dark:bg-[#272727] dark:text-white'
                    : 'rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-zinc-400';
            });
            executeSemanticSearch();
        });
    }

    if (dom.semanticConceptsChips) {
        dom.semanticConceptsChips.addEventListener('click', function (e) {
            const chip = e.target.closest('[data-semantic-chip]');
            if (!chip) return;
            const term = chip.dataset.semanticChip || '';
            state.decisionSearchQuery = term;
            if (dom.decisionSearchInput) {
                dom.decisionSearchInput.value = term;
            }
            executeSemanticSearch();
        });
    }

    if (dom.decisionCategoryFilters) {
        dom.decisionCategoryFilters.addEventListener('click', function (e) {
            const pill = e.target.closest('[data-cat-filter]');
            if (!pill) return;
            state.decisionCategoryFilter = pill.dataset.catFilter || '';
            dom.decisionCategoryFilters.querySelectorAll('[data-cat-filter]').forEach(function (el) {
                const active = el === pill;
                el.setAttribute('aria-pressed', active ? 'true' : 'false');
                el.className = active
                    ? 'rounded-lg border border-slate-200 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white dark:border-[#313131] dark:bg-[#313131] dark:text-white'
                    : 'rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-[#313131] dark:bg-[#272727] dark:text-zinc-300';
            });
            executeSemanticSearch();
        });
    }

    if (dom.formCreateDecision) {
        dom.formCreateDecision.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!state.meeting) return;
            const title = dom.newDecisionTitle.value.trim();
            const rationale = dom.newDecisionRationale.value.trim();
            if (!title || !rationale) return;

            const response = await fetch(baseUrl + '/api/decisions', {
                method: 'POST',
                headers: buildHeaders(true),
                body: JSON.stringify({
                    meeting_id: state.meeting.id,
                    title: title,
                    rationale: rationale,
                    category: dom.newDecisionCategory.value || 'decision',
                    speaker: dom.newDecisionSpeaker.value || (i18n.taskDefaultAssignee || 'Equipo'),
                    timestamp_label: dom.newDecisionTimestamp.value || '00:00',
                    semantic_tags: dom.newDecisionTags.value.trim()
                })
            });
            const data = await response.json();
            if (response.ok && data.decision) {
                state.decisions.push(data.decision);
                dom.newDecisionTitle.value = '';
                dom.newDecisionRationale.value = '';
                dom.newDecisionTags.value = '';
                if (dom.detailsNewDecision) dom.detailsNewDecision.open = false;
                await executeSemanticSearch();
                showBanner(data.message || i18n.decisionCreatedMsg || '');
            }
        });
    }

    if (dom.decisionsList) {
        dom.decisionsList.addEventListener('click', async function (e) {
            const jumpBtn = e.target.closest('.js-jump-minute');
            if (jumpBtn) {
                await jumpToTranscriptLine(
                    jumpBtn.dataset.entryId,
                    jumpBtn.dataset.timestamp,
                    jumpBtn.dataset.meetingId
                );
                return;
            }

            const delBtn = e.target.closest('.js-delete-decision');
            if (delBtn) {
                const decId = delBtn.dataset.decisionId;
                const response = await fetch(baseUrl + '/api/decisions/' + encodeURIComponent(decId), {
                    method: 'DELETE',
                    headers: buildHeaders(true)
                });
                if (response.ok) {
                    state.decisions = state.decisions.filter(function (d) { return String(d.id) !== String(decId); });
                    await executeSemanticSearch();
                    showBanner(i18n.decisionDeletedMsg || '');
                }
            }
        });
    }

    // 11. Visor Cronológico: Filtro Instantáneo y Acciones en 1 Clic (+ Tarea / + Decisión)
    if (dom.transcriptFilterInput) {
        dom.transcriptFilterInput.addEventListener('input', function () {
            state.transcriptFilterText = dom.transcriptFilterInput.value;
            renderTranscript();
        });
    }

    if (dom.transcriptList) {
        dom.transcriptList.addEventListener('click', async function (e) {
            if (!state.meeting) return;

            const quickTaskBtn = e.target.closest('.js-quick-task');
            if (quickTaskBtn) {
                const entryId = quickTaskBtn.dataset.entryId;
                const entry = state.entries.find(function (en) { return String(en.id) === String(entryId); });
                if (!entry) return;

                quickTaskBtn.disabled = true;
                try {
                    const response = await fetch(baseUrl + '/api/tasks', {
                        method: 'POST',
                        headers: buildHeaders(true),
                        body: JSON.stringify({
                            meeting_id: state.meeting.id,
                            entry_id: entry.id,
                            timestamp_label: entry.timestamp_label || '00:00',
                            assignee: entry.speaker || (i18n.taskDefaultAssignee || 'Equipo'),
                            summary: entry.content || ''
                        })
                    });
                    const data = await response.json();
                    if (response.ok && data.task) {
                        state.tasks.push(data.task);
                        renderTasks();
                        showBanner(i18n.taskCreatedMsg || '');
                    }
                } finally {
                    quickTaskBtn.disabled = false;
                }
                return;
            }

            const quickDecisionBtn = e.target.closest('.js-quick-decision');
            if (quickDecisionBtn) {
                const entryId = quickDecisionBtn.dataset.entryId;
                const entry = state.entries.find(function (en) { return String(en.id) === String(entryId); });
                if (!entry) return;

                const rawText = (entry.content || '').trim();
                const shortTitle = rawText.length > 90 ? rawText.slice(0, 87) + '...' : rawText;

                quickDecisionBtn.disabled = true;
                try {
                    const response = await fetch(baseUrl + '/api/decisions', {
                        method: 'POST',
                        headers: buildHeaders(true),
                        body: JSON.stringify({
                            meeting_id: state.meeting.id,
                            entry_id: entry.id,
                            category: 'decision',
                            title: shortTitle || 'Acuerdo de reunión',
                            rationale: rawText,
                            speaker: entry.speaker || (i18n.taskDefaultAssignee || 'Equipo'),
                            timestamp_label: entry.timestamp_label || '00:00'
                        })
                    });
                    const data = await response.json();
                    if (response.ok && data.decision) {
                        state.decisions.push(data.decision);
                        renderDecisions(state.decisions, []);
                        showBanner(data.message || i18n.decisionCreatedMsg || '');
                    }
                } finally {
                    quickDecisionBtn.disabled = false;
                }
            }
        });
    }

    // Inicialización de preferencia de sidebar y datos precargados
    try {
        if (localStorage.getItem('sidebar_collapsed') === '1') {
            setSidebarCollapsed(true);
        }
    } catch (e) {}

    hydrateMeetingData(initialConfig.activeMeetingData);
})();
