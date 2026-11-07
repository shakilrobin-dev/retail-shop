{{-- Styles for the official report document. Plain CSS that both browsers and mPDF understand. --}}
<style>
    .rpt { color: #1f2937; font-size: 10pt; line-height: 1.4; }
    .rpt table { width: 100%; border-collapse: collapse; }
    .rpt-letterhead td { vertical-align: middle; padding: 0; }
    .rpt-logo { width: 64px; padding-right: 12px !important; }
    .rpt-logo img { max-height: 56px; max-width: 64px; }
    .rpt-shop { font-size: 17pt; font-weight: bold; color: #1e1b4b; letter-spacing: 0.2px; }
    .rpt-tagline { font-size: 9pt; color: #4f46e5; margin-top: 1px; }
    .rpt-small { font-size: 8.5pt; color: #6b7280; }
    .rpt-contact { text-align: right; font-size: 8.5pt; color: #4b5563; width: 34%; }
    .rpt-rule { border-top: 2.5px solid #1e1b4b; border-bottom: 1px solid #4f46e5; height: 2px; margin: 10px 0 14px; }
    .rpt-title { text-align: center; font-size: 13pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #111827; }
    .rpt-period { text-align: center; font-size: 9pt; color: #4b5563; margin: 2px 0 10px; }
    .rpt-meta { margin-bottom: 12px; border: 1px solid #d1d5db; }
    .rpt-meta td { padding: 5px 8px; font-size: 8.5pt; border-right: 1px solid #e5e7eb; vertical-align: top; }
    .rpt-meta-label { font-size: 7pt; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
    .rpt-table { font-size: 8.5pt; }
    .rpt-table th { background: #eef0f6; color: #1e1b4b; font-weight: bold; text-align: left; padding: 6px 6px; border-top: 1.5px solid #1e1b4b; border-bottom: 1px solid #1e1b4b; white-space: nowrap; }
    .rpt-table td { padding: 4px 6px; border-bottom: 0.5px solid #e5e7eb; vertical-align: top; }
    .rpt-table tr.rpt-even td { background: #f9fafb; }
    .rpt-table tfoot td { font-weight: bold; border-top: 1.5px solid #1e1b4b; border-bottom: 3px double #1e1b4b; background: #eef0f6; padding: 6px; }
    .rpt-sl { width: 28px; color: #6b7280; text-align: center !important; }
    .rpt-r { text-align: right !important; }
    .rpt-num { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .rpt-empty { text-align: center; padding: 22px !important; color: #6b7280; }
    .rpt-count { font-size: 8pt; color: #6b7280; margin-top: 4px; text-align: right; }
    .rpt-statement { margin-top: 10px; font-size: 9.5pt; }
    .rpt-statement td { padding: 4px 8px; }
    .rpt-section { font-weight: bold; color: #1e1b4b; border-bottom: 1px solid #1e1b4b; padding-top: 8px !important; text-transform: uppercase; font-size: 8.5pt; letter-spacing: 0.5px; }
    .rpt-line-plain td { border-bottom: 0.5px solid #f3f4f6; }
    .rpt-line-muted td { color: #6b7280; border-bottom: 0.5px solid #f3f4f6; }
    .rpt-line-subtotal td { font-weight: bold; border-top: 1px solid #9ca3af; }
    .rpt-line-total td { font-weight: bold; border-top: 1px solid #1e1b4b; background: #f3f4f6; }
    .rpt-line-grand td { font-weight: bold; font-size: 11pt; color: #1e1b4b; border-top: 1.5px solid #1e1b4b; border-bottom: 3px double #1e1b4b; background: #eef0f6; padding: 7px 8px; }
    .rpt-notes { margin-top: 14px; font-size: 8.5pt; color: #4b5563; border-left: 3px solid #4f46e5; padding: 4px 10px; background: #f9fafb; }
    .rpt-notes-title { font-weight: bold; color: #1f2937; margin-bottom: 2px; }
    .rpt-signatures { margin-top: 48px; }
    .rpt-signatures td { width: 33%; text-align: center; padding: 0 18px; vertical-align: top; }
    .rpt-sign-line { border-top: 1px solid #374151; height: 1px; margin-bottom: 4px; }
    .rpt-sign-role { font-weight: bold; font-size: 9pt; }
    .rpt-footer { margin-top: 26px; border-top: 1px solid #d1d5db; font-size: 7.5pt; color: #6b7280; }
    .rpt-footer td { padding-top: 5px; }
    .rpt-running { font-size: 7.5pt; color: #6b7280; border-bottom: 0.5px solid #d1d5db; padding-bottom: 3px; }
</style>
