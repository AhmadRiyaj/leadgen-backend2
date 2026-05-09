import { useState, useEffect } from "react";

// ─── Mock data (replace with real API calls) ──────────────────────────────────
const MOCK_LEADS = [
  { id: 1, business_name: "Sharma Medical Clinic", category: "medical clinic", city: "Delhi", mobile: "9876543210", has_website: false, lead_score: 9, status: "replied", last_contacted_at: "2026-05-07" },
  { id: 2, business_name: "Ravi Electronics", category: "electronics store", city: "Mumbai", mobile: "9123456780", has_website: false, lead_score: 8, status: "interested", last_contacted_at: "2026-05-06" },
  { id: 3, business_name: "Star Coaching Centre", category: "coaching centre", city: "Jaipur", mobile: "9988776655", has_website: false, lead_score: 9, status: "message_sent", last_contacted_at: "2026-05-08" },
  { id: 4, business_name: "Gupta General Store", category: "grocery store", city: "Lucknow", mobile: "9871234560", has_website: false, lead_score: 7, status: "new", last_contacted_at: null },
  { id: 5, business_name: "Sunrise Travel Agency", category: "travel agency", city: "Bangalore", mobile: "9765432100", has_website: true, lead_score: 5, status: "not_interested", last_contacted_at: "2026-05-05" },
  { id: 6, business_name: "FitZone Gym", category: "gym fitness centre", city: "Pune", mobile: "9654321000", has_website: false, lead_score: 8, status: "message_sent", last_contacted_at: "2026-05-07" },
  { id: 7, business_name: "Royal Salon", category: "beauty salon", city: "Chennai", mobile: "9543210000", has_website: false, lead_score: 7, status: "new", last_contacted_at: null },
  { id: 8, business_name: "Patel Hardware", category: "hardware shop", city: "Ahmedabad", mobile: "9432100000", has_website: false, lead_score: 8, status: "meeting", last_contacted_at: "2026-05-04" },
  { id: 9, business_name: "City Auto Repair", category: "automobile repair shop", city: "Hyderabad", mobile: "9321000000", has_website: false, lead_score: 6, status: "message_sent", last_contacted_at: "2026-05-06" },
  { id: 10, business_name: "Modern Restaurant", category: "local restaurant", city: "Kolkata", mobile: "9210000000", has_website: true, lead_score: 5, status: "client", last_contacted_at: "2026-04-28" },
];

const STATUS_META = {
  new:            { label: "New",          color: "#6366f1", bg: "#eef2ff" },
  scored:         { label: "Scored",       color: "#8b5cf6", bg: "#f5f3ff" },
  message_sent:   { label: "Msg Sent",     color: "#0891b2", bg: "#ecfeff" },
  replied:        { label: "Replied",      color: "#059669", bg: "#ecfdf5" },
  interested:     { label: "Interested",   color: "#d97706", bg: "#fffbeb" },
  meeting:        { label: "Meeting",      color: "#ea580c", bg: "#fff7ed" },
  proposal:       { label: "Proposal",     color: "#dc2626", bg: "#fef2f2" },
  client:         { label: "Client",       color: "#15803d", bg: "#f0fdf4" },
  not_interested: { label: "No Interest",  color: "#9ca3af", bg: "#f9fafb" },
};

const PIPELINE_STAGES = ["new","message_sent","replied","interested","meeting","proposal","client"];

function Badge({ status }) {
  const m = STATUS_META[status] || STATUS_META.new;
  return (
    <span style={{
      background: m.bg, color: m.color,
      fontSize: 11, fontWeight: 600, padding: "2px 8px",
      borderRadius: 20, letterSpacing: ".3px", whiteSpace: "nowrap",
    }}>{m.label}</span>
  );
}

function ScoreDot({ score }) {
  const color = score >= 8 ? "#15803d" : score >= 6 ? "#d97706" : "#9ca3af";
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 4 }}>
      <span style={{ width: 8, height: 8, borderRadius: "50%", background: color, display: "inline-block" }} />
      <span style={{ fontWeight: 600, color, fontSize: 13 }}>{score}/10</span>
    </span>
  );
}

function StatCard({ label, value, sub, accent }) {
  return (
    <div style={{
      background: "#fff", borderRadius: 12, padding: "16px 20px",
      border: "1px solid #f0f0f0", flex: 1, minWidth: 140,
    }}>
      <div style={{ fontSize: 12, color: "#9ca3af", marginBottom: 4 }}>{label}</div>
      <div style={{ fontSize: 28, fontWeight: 700, color: accent || "#111" }}>{value}</div>
      {sub && <div style={{ fontSize: 11, color: "#9ca3af", marginTop: 2 }}>{sub}</div>}
    </div>
  );
}

function PipelineBar({ leads }) {
  const counts = {};
  PIPELINE_STAGES.forEach(s => { counts[s] = 0; });
  leads.forEach(l => { if (counts[l.status] !== undefined) counts[l.status]++; });
  const max = Math.max(...Object.values(counts), 1);

  return (
    <div style={{ background: "#fff", borderRadius: 12, padding: "20px 24px", border: "1px solid #f0f0f0" }}>
      <div style={{ fontWeight: 600, fontSize: 14, marginBottom: 16, color: "#111" }}>Pipeline funnel</div>
      <div style={{ display: "flex", gap: 6, alignItems: "flex-end", height: 80 }}>
        {PIPELINE_STAGES.map(s => {
          const m = STATUS_META[s];
          const h = Math.max((counts[s] / max) * 64, 4);
          return (
            <div key={s} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 4 }}>
              <div style={{ fontSize: 11, fontWeight: 600, color: m.color }}>{counts[s]}</div>
              <div style={{ width: "100%", height: h, background: m.color, borderRadius: 4, opacity: .85, transition: "height .3s" }} />
              <div style={{ fontSize: 9, color: "#9ca3af", textAlign: "center", lineHeight: 1.2 }}>{m.label}</div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

export default function Dashboard() {
  const [leads, setLeads] = useState(MOCK_LEADS);
  const [search, setSearch] = useState("");
  const [filterCity, setFilterCity] = useState("All");
  const [filterStatus, setFilterStatus] = useState("All");
  const [filterCat, setFilterCat] = useState("All");
  const [selected, setSelected] = useState(null);
  const [aiReply, setAiReply] = useState("");
  const [aiLoading, setAiLoading] = useState(false);

  const cities = ["All", ...new Set(MOCK_LEADS.map(l => l.city))];
  const cats   = ["All", ...new Set(MOCK_LEADS.map(l => l.category))];
  const statuses = ["All", ...Object.keys(STATUS_META)];

  const filtered = leads.filter(l => {
    if (search && !l.business_name.toLowerCase().includes(search.toLowerCase()) &&
        !l.city.toLowerCase().includes(search.toLowerCase())) return false;
    if (filterCity !== "All" && l.city !== filterCity) return false;
    if (filterStatus !== "All" && l.status !== filterStatus) return false;
    if (filterCat !== "All" && l.category !== filterCat) return false;
    return true;
  });

  const stats = {
    total: leads.length,
    sent: leads.filter(l => l.status !== "new" && l.status !== "not_interested").length,
    replied: leads.filter(l => ["replied","interested","meeting","proposal","client"].includes(l.status)).length,
    clients: leads.filter(l => l.status === "client").length,
  };

  async function getAiReply(lead) {
    setAiLoading(true);
    setAiReply("");
    try {
      const res = await fetch("https://api.anthropic.com/v1/messages", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          model: "claude-sonnet-4-20250514",
          max_tokens: 1000,
          messages: [{
            role: "user",
            content: `You are a B2B sales assistant for an Indian software company. Write a short follow-up WhatsApp reply to a business owner.\n\nBusiness: ${lead.business_name}\nCategory: ${lead.category}\nCity: ${lead.city}\nCurrent status: ${lead.status}\n\nWrite ONE short, friendly WhatsApp message in Hinglish (mix of Hindi and English). Max 3 sentences. Offer a free demo or consultation. Do not use emojis.`
          }]
        })
      });
      const data = await res.json();
      setAiReply(data.content?.[0]?.text || "Could not generate reply.");
    } catch (e) {
      setAiReply("Error connecting to AI. Check your API key.");
    }
    setAiLoading(false);
  }

  function updateStatus(id, newStatus) {
    setLeads(prev => prev.map(l => l.id === id ? { ...l, status: newStatus } : l));
    if (selected?.id === id) setSelected(prev => ({ ...prev, status: newStatus }));
  }

  const sel = selected ? leads.find(l => l.id === selected.id) || selected : null;

  return (
    <div style={{ fontFamily: "system-ui, sans-serif", background: "#f8f9fa", minHeight: "100vh", padding: 20 }}>
      {/* Header */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
        <div>
          <div style={{ fontSize: 22, fontWeight: 700, color: "#111" }}>LeadGen CRM</div>
          <div style={{ fontSize: 13, color: "#9ca3af" }}>AI-powered B2B outreach — Pan India</div>
        </div>
        <div style={{ fontSize: 12, color: "#9ca3af", background: "#fff", padding: "6px 14px", borderRadius: 20, border: "1px solid #f0f0f0" }}>
          {new Date().toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}
        </div>
      </div>

      {/* Stat cards */}
      <div style={{ display: "flex", gap: 12, marginBottom: 20, flexWrap: "wrap" }}>
        <StatCard label="Total leads" value={stats.total} sub="across all cities" />
        <StatCard label="Contacted" value={stats.sent} sub="messages sent" accent="#0891b2" />
        <StatCard label="Replied" value={stats.replied} sub="engaged leads" accent="#059669" />
        <StatCard label="Clients" value={stats.clients} sub="converted" accent="#15803d" />
      </div>

      {/* Pipeline */}
      <div style={{ marginBottom: 20 }}>
        <PipelineBar leads={leads} />
      </div>

      {/* Filters + Table */}
      <div style={{ background: "#fff", borderRadius: 12, border: "1px solid #f0f0f0", overflow: "hidden" }}>
        {/* Filter bar */}
        <div style={{ display: "flex", gap: 10, padding: "14px 20px", borderBottom: "1px solid #f5f5f5", flexWrap: "wrap", alignItems: "center" }}>
          <input
            placeholder="Search business or city..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            style={{ flex: 1, minWidth: 180, padding: "7px 12px", borderRadius: 8, border: "1px solid #e5e7eb", fontSize: 13, outline: "none" }}
          />
          <select value={filterCity} onChange={e => setFilterCity(e.target.value)}
            style={{ padding: "7px 10px", borderRadius: 8, border: "1px solid #e5e7eb", fontSize: 13, cursor: "pointer" }}>
            {cities.map(c => <option key={c}>{c}</option>)}
          </select>
          <select value={filterStatus} onChange={e => setFilterStatus(e.target.value)}
            style={{ padding: "7px 10px", borderRadius: 8, border: "1px solid #e5e7eb", fontSize: 13, cursor: "pointer" }}>
            {statuses.map(s => <option key={s} value={s}>{s === "All" ? "All statuses" : STATUS_META[s]?.label || s}</option>)}
          </select>
          <select value={filterCat} onChange={e => setFilterCat(e.target.value)}
            style={{ padding: "7px 10px", borderRadius: 8, border: "1px solid #e5e7eb", fontSize: 13, cursor: "pointer" }}>
            {cats.map(c => <option key={c}>{c}</option>)}
          </select>
          <span style={{ fontSize: 12, color: "#9ca3af" }}>{filtered.length} leads</span>
        </div>

        {/* Table */}
        <div style={{ overflowX: "auto" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
            <thead>
              <tr style={{ background: "#fafafa" }}>
                {["Business", "Category", "City", "Mobile", "Website", "Score", "Status", "Actions"].map(h => (
                  <th key={h} style={{ padding: "10px 16px", textAlign: "left", fontWeight: 600, color: "#6b7280", fontSize: 11, letterSpacing: ".5px", whiteSpace: "nowrap" }}>{h.toUpperCase()}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {filtered.map((lead, i) => (
                <tr key={lead.id} style={{ borderTop: "1px solid #f5f5f5", background: sel?.id === lead.id ? "#f0f9ff" : i % 2 === 0 ? "#fff" : "#fdfeff" }}>
                  <td style={{ padding: "10px 16px", fontWeight: 500, color: "#111" }}>{lead.business_name}</td>
                  <td style={{ padding: "10px 16px", color: "#6b7280" }}>{lead.category}</td>
                  <td style={{ padding: "10px 16px", color: "#6b7280" }}>{lead.city}</td>
                  <td style={{ padding: "10px 16px", color: "#6b7280" }}>{lead.mobile}</td>
                  <td style={{ padding: "10px 16px" }}>
                    <span style={{ color: lead.has_website ? "#059669" : "#dc2626", fontWeight: 600, fontSize: 12 }}>
                      {lead.has_website ? "Yes" : "No"}
                    </span>
                  </td>
                  <td style={{ padding: "10px 16px" }}><ScoreDot score={lead.lead_score} /></td>
                  <td style={{ padding: "10px 16px" }}><Badge status={lead.status} /></td>
                  <td style={{ padding: "10px 16px" }}>
                    <button
                      onClick={() => { setSelected(lead); setAiReply(""); }}
                      style={{ padding: "4px 10px", borderRadius: 6, border: "1px solid #e5e7eb", background: "#fff", fontSize: 12, cursor: "pointer", color: "#374151" }}
                    >
                      View
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {filtered.length === 0 && (
            <div style={{ padding: 40, textAlign: "center", color: "#9ca3af", fontSize: 13 }}>No leads match your filters.</div>
          )}
        </div>
      </div>

      {/* Lead detail panel */}
      {sel && (
        <div style={{
          position: "fixed", right: 0, top: 0, bottom: 0, width: 380,
          background: "#fff", boxShadow: "-4px 0 24px rgba(0,0,0,.08)",
          overflow: "auto", zIndex: 50, padding: 24,
        }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 20 }}>
            <div>
              <div style={{ fontWeight: 700, fontSize: 16, color: "#111" }}>{sel.business_name}</div>
              <div style={{ fontSize: 12, color: "#9ca3af" }}>{sel.category} · {sel.city}</div>
            </div>
            <button onClick={() => { setSelected(null); setAiReply(""); }}
              style={{ background: "none", border: "none", fontSize: 20, cursor: "pointer", color: "#9ca3af", lineHeight: 1 }}>×</button>
          </div>

          {/* Details */}
          {[
            ["Mobile", sel.mobile],
            ["Website", sel.has_website ? sel.website || "Yes" : "None — strong lead"],
            ["Lead score", `${sel.lead_score}/10`],
            ["Last contacted", sel.last_contacted_at || "Never"],
          ].map(([k, v]) => (
            <div key={k} style={{ display: "flex", justifyContent: "space-between", padding: "8px 0", borderBottom: "1px solid #f5f5f5", fontSize: 13 }}>
              <span style={{ color: "#9ca3af" }}>{k}</span>
              <span style={{ fontWeight: 500, color: "#111" }}>{v}</span>
            </div>
          ))}

          {/* Status update */}
          <div style={{ marginTop: 20 }}>
            <div style={{ fontSize: 12, fontWeight: 600, color: "#6b7280", marginBottom: 8 }}>UPDATE STATUS</div>
            <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>
              {Object.entries(STATUS_META).map(([k, m]) => (
                <button key={k}
                  onClick={() => updateStatus(sel.id, k)}
                  style={{
                    padding: "4px 10px", borderRadius: 20, fontSize: 11, fontWeight: 600, cursor: "pointer",
                    background: sel.status === k ? m.color : m.bg,
                    color: sel.status === k ? "#fff" : m.color,
                    border: `1px solid ${m.color}`,
                  }}>
                  {m.label}
                </button>
              ))}
            </div>
          </div>

          {/* AI reply generator */}
          <div style={{ marginTop: 24, background: "#f8f9fa", borderRadius: 10, padding: 16 }}>
            <div style={{ fontSize: 12, fontWeight: 600, color: "#6b7280", marginBottom: 10 }}>AI FOLLOW-UP GENERATOR</div>
            <button
              onClick={() => getAiReply(sel)}
              disabled={aiLoading}
              style={{
                width: "100%", padding: "9px 0", borderRadius: 8,
                background: aiLoading ? "#e5e7eb" : "#111", color: aiLoading ? "#9ca3af" : "#fff",
                border: "none", fontWeight: 600, fontSize: 13, cursor: aiLoading ? "not-allowed" : "pointer",
              }}>
              {aiLoading ? "Generating..." : "Generate WhatsApp reply"}
            </button>
            {aiReply && (
              <div style={{ marginTop: 12, background: "#fff", borderRadius: 8, padding: 12, fontSize: 13, color: "#374151", lineHeight: 1.6, border: "1px solid #e5e7eb" }}>
                {aiReply}
                <button
                  onClick={() => navigator.clipboard?.writeText(aiReply)}
                  style={{ marginTop: 8, display: "block", fontSize: 11, color: "#0891b2", background: "none", border: "none", cursor: "pointer", padding: 0 }}>
                  Copy to clipboard
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
