/* ============================================================
   AB ENGINS SARL — Assistant IA (chat branché sur api/agent.php)
   ============================================================ */
"use strict";

const chatHistory = []; // {role: "user"|"assistant", content: string}

function escapeHtml(s) {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

// Rendu minimal du markdown renvoyé par l'assistant : gras, puces, sauts de ligne.
function renderReply(text) {
  const lines = escapeHtml(text).split("\n");
  let html = "";
  let inList = false;
  for (const line of lines) {
    const bullet = line.match(/^\s*[-*•]\s+(.*)$/);
    if (bullet) {
      if (!inList) { html += "<ul>"; inList = true; }
      html += `<li>${bullet[1]}</li>`;
    } else {
      if (inList) { html += "</ul>"; inList = false; }
      if (line.trim() !== "") html += `<p>${line}</p>`;
    }
  }
  if (inList) html += "</ul>";
  return html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
}

function appendMessage(role, html) {
  const box = $("#chatMessages");
  const msg = el(`<div class="chat-msg ${role === "user" ? "chat-msg-user" : "chat-msg-bot"}">${html}</div>`);
  box.appendChild(msg);
  box.scrollTop = box.scrollHeight;
  return msg;
}

function setBusy(busy) {
  $("#chatInput").disabled = busy;
  $("#chatSend").disabled = busy;
}

async function sendQuestion(question) {
  const errorBox = $("#chatError");
  errorBox.hidden = true;
  $("#chatSuggestions").hidden = true;

  chatHistory.push({ role: "user", content: question });
  appendMessage("user", `<p>${escapeHtml(question)}</p>`);
  const pending = appendMessage("bot", `<p class="chat-typing">Consultation des données…</p>`);
  setBusy(true);

  try {
    const data = await apiFetch("api/agent.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ messages: chatHistory }),
    });
    chatHistory.push({ role: "assistant", content: data.reply });
    pending.innerHTML = renderReply(data.reply);
  } catch (e) {
    chatHistory.pop(); // la question n'a pas abouti : on la retire de l'historique
    pending.remove();
    errorBox.textContent = "Erreur : " + e.message;
    errorBox.hidden = false;
  } finally {
    setBusy(false);
    $("#chatInput").focus();
    $("#chatMessages").scrollTop = $("#chatMessages").scrollHeight;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  $("#chatForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const input = $("#chatInput");
    const q = input.value.trim();
    if (!q || input.disabled) return;
    input.value = "";
    sendQuestion(q);
  });

  document.querySelectorAll("#chatSuggestions button").forEach((btn) => {
    btn.addEventListener("click", () => sendQuestion(btn.dataset.q));
  });

  $("#chatInput").focus();
});
