/**
 * HubSpot Form Config Block
 * 
 * Renders form configuration UI in the main editor area.
 */
(function() {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var __ = wp.i18n.__;
	var Button = wp.components.Button;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var Card = wp.components.Card;
	var CardHeader = wp.components.CardHeader;
	var CardBody = wp.components.CardBody;
	var apiFetch = wp.apiFetch;

	registerBlockType("bb-forms-for-hubspot/form-config", {
		title: __("HubSpot Form Config", "bb-forms-for-hubspot"),
		icon: "feedback",
		category: "widgets",
		supports: {
			html: false,
			reusable: false,
			multiple: false,
		},

		edit: function(props) {
			var postId = useSelect(function(select) {
				return select("core/editor").getCurrentPostId();
			}, []);
			var meta = useSelect(function(select) {
				return select("core/editor").getEditedPostAttribute("meta") || {};
			}, []);
			var editPost = useDispatch("core/editor").editPost;

			var formsState = useState([]);
			var forms = formsState[0];
			var setForms = formsState[1];

			var loadingState = useState(true);
			var loading = loadingState[0];
			var setLoading = loadingState[1];

			var syncingState = useState(false);
			var syncing = syncingState[0];
			var setSyncing = syncingState[1];

			var errorState = useState("");
			var error = errorState[0];
			var setError = errorState[1];

			var copiedState = useState(false);
			var copied = copiedState[0];
			var setCopied = copiedState[1];

			var formGuid = meta._bbhs_form_guid || "";
			var schema = meta._bbhs_schema || {};
			var overrides = meta._bbhs_overrides || { order: [], hidden: [], labels: {} };
			var schemaFields = schema.fields || [];
			var fetchedAt = schema.fetchedAt || 0;

			// Load forms on mount.
			useEffect(function() {
				apiFetch({ path: "/bb-hubspot/v1/forms" })
					.then(function(response) {
						if (response.success && response.forms) {
							setForms(response.forms);
						} else {
							setError(response.error || __("Unable to load forms.", "bb-forms-for-hubspot"));
						}
					})
					.catch(function(err) {
						var msg = (err && err.error) || (err && err.message) || __("Unable to load forms.", "bb-forms-for-hubspot");
						setError(msg);
					})
					.finally(function() {
						setLoading(false);
					});
			}, []);

			var handleFormChange = function(value) {
				editPost({ meta: Object.assign({}, meta, { _bbhs_form_guid: value }) });
			};

			var handleSync = function() {
				if (!formGuid) return;
				setSyncing(true);
				setError("");
				apiFetch({
					path: "/bb-hubspot/v1/forms/schema",
					method: "POST",
					data: { formGuid: formGuid },
				})
					.then(function(response) {
						if (response.success && response.schema) {
							editPost({
								meta: Object.assign({}, meta, {
									_bbhs_schema: response.schema,
									_bbhs_form_guid: formGuid,
								}),
							});
						} else {
							setError(response.error || __("Unable to fetch schema.", "bb-forms-for-hubspot"));
						}
					})
					.catch(function(err) {
						var msg = (err && err.error) || (err && err.message) || __("Unable to fetch schema.", "bb-forms-for-hubspot");
						setError(msg);
					})
					.finally(function() {
						setSyncing(false);
					});
			};

			var getOrderedFields = function() {
				if (!schemaFields.length) return [];
				var order = overrides.order || [];
				var fieldMap = {};
				schemaFields.forEach(function(f) { fieldMap[f.name] = f; });
				var ordered = [];
				order.forEach(function(name) {
					if (fieldMap[name]) {
						ordered.push(fieldMap[name]);
						delete fieldMap[name];
					}
				});
				schemaFields.forEach(function(f) {
					if (fieldMap[f.name]) ordered.push(f);
				});
				return ordered;
			};

			var orderedFields = getOrderedFields();
			var isHidden = function(name) { return (overrides.hidden || []).indexOf(name) !== -1; };
			var getLabel = function(field) { return (overrides.labels || {})[field.name] || field.label; };

			var updateOverrides = function(newOverrides) {
				editPost({ meta: Object.assign({}, meta, { _bbhs_overrides: Object.assign({}, overrides, newOverrides) }) });
			};

			var toggleHidden = function(name) {
				var hidden = overrides.hidden || [];
				var newHidden = hidden.indexOf(name) !== -1 ? hidden.filter(function(n) { return n !== name; }) : hidden.concat([name]);
				updateOverrides({ hidden: newHidden });
			};

			var updateLabel = function(name, label) {
				var labels = Object.assign({}, overrides.labels || {});
				labels[name] = label;
				updateOverrides({ labels: labels });
			};

			var moveField = function(index, direction) {
				var order = orderedFields.map(function(f) { return f.name; });
				var newIndex = index + direction;
				if (newIndex < 0 || newIndex >= order.length) return;
				var temp = order[index];
				order[index] = order[newIndex];
				order[newIndex] = temp;
				updateOverrides({ order: order });
			};

			var handleCopy = function() {
				var shortcode = '[bb_hubspot_form id="' + postId + '"]';
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(shortcode).then(function() {
						setCopied(true);
						setTimeout(function() { setCopied(false); }, 2000);
					});
				}
			};

			var formOptions = [{ value: "", label: __("— Select a HubSpot form —", "bb-forms-for-hubspot") }];
			forms.forEach(function(f) {
				formOptions.push({ value: f.id, label: f.name || f.id });
			});

			var lastSynced = fetchedAt ? new Date(fetchedAt * 1000).toLocaleString() : __("Never", "bb-forms-for-hubspot");
			var shortcode = postId ? '[bb_hubspot_form id="' + postId + '"]' : "";

			// Main container styles
			var containerStyle = {
				maxWidth: "900px",
				margin: "0 auto",
				padding: "24px",
			};

			var gridStyle = {
				display: "grid",
				gridTemplateColumns: "1fr 1fr",
				gap: "24px",
			};

			var cardStyle = {
				background: "#fff",
				border: "1px solid #ddd",
				borderRadius: "8px",
				overflow: "hidden",
			};

			var cardHeaderStyle = {
				background: "#f6f7f7",
				padding: "12px 16px",
				borderBottom: "1px solid #ddd",
				fontWeight: "600",
				fontSize: "14px",
			};

			var cardBodyStyle = {
				padding: "16px",
			};

			var fullWidthCardStyle = Object.assign({}, cardStyle, { gridColumn: "1 / -1" });

			// Build field rows
			var fieldRows = orderedFields.map(function(field, index) {
				var hidden = isHidden(field.name);
				return el("div", {
					key: field.name,
					style: {
						display: "flex",
						alignItems: "center",
						gap: "12px",
						padding: "12px",
						marginBottom: "8px",
						background: hidden ? "#f9f9f9" : "#fff",
						border: "1px solid #e0e0e0",
						borderRadius: "4px",
						opacity: hidden ? 0.6 : 1,
					},
				},
					el("div", { style: { display: "flex", flexDirection: "column", gap: "2px" } },
						el(Button, { isSmall: true, icon: "arrow-up-alt2", label: __("Move up", "bb-forms-for-hubspot"), onClick: function() { moveField(index, -1); }, disabled: index === 0 }),
						el(Button, { isSmall: true, icon: "arrow-down-alt2", label: __("Move down", "bb-forms-for-hubspot"), onClick: function() { moveField(index, 1); }, disabled: index === orderedFields.length - 1 })
					),
					el("div", { style: { flex: 1, minWidth: 0 } },
						el(TextControl, {
							label: __("Label", "bb-forms-for-hubspot"),
							value: getLabel(field),
							onChange: function(val) { updateLabel(field.name, val); },
							__nextHasNoMarginBottom: true,
						}),
						el("div", { style: { fontSize: "12px", color: "#666", marginTop: "4px", display: "flex", gap: "8px", flexWrap: "wrap" } },
							el("code", { style: { background: "#f0f0f0", padding: "2px 6px", borderRadius: "3px" } }, field.name),
							el("span", { style: { background: "#e0e0e0", padding: "2px 8px", borderRadius: "12px" } }, field.type),
							field.required && el("span", { style: { background: "#d63638", color: "#fff", padding: "2px 8px", borderRadius: "12px" } }, __("Required", "bb-forms-for-hubspot"))
						)
					),
					el(ToggleControl, {
						label: __("Show", "bb-forms-for-hubspot"),
						checked: !hidden,
						onChange: function() { toggleHidden(field.name); },
						__nextHasNoMarginBottom: true,
					})
				);
			});

			return el("div", { style: containerStyle },
				el("div", { style: gridStyle },
					// Form Selection Card
					el("div", { style: cardStyle },
						el("div", { style: cardHeaderStyle }, __("HubSpot Form", "bb-forms-for-hubspot")),
						el("div", { style: cardBodyStyle },
							el("p", { style: { color: "#666", marginTop: 0 } },
								__("Select a form from your HubSpot account to mirror in WordPress.", "bb-forms-for-hubspot")
							),
							loading ? el(Spinner) : el(SelectControl, {
								value: formGuid,
								options: formOptions,
								onChange: handleFormChange,
								__nextHasNoMarginBottom: true,
							}),
							el("div", { style: { marginTop: "12px", display: "flex", alignItems: "center", gap: "12px" } },
								el(Button, {
									variant: "secondary",
									onClick: handleSync,
									disabled: !formGuid || syncing,
									isBusy: syncing,
								}, syncing ? __("Syncing…", "bb-forms-for-hubspot") : __("Sync fields from HubSpot", "bb-forms-for-hubspot")),
								el("span", { style: { color: "#666", fontSize: "12px" } }, __("Last synced:", "bb-forms-for-hubspot") + " " + lastSynced)
							),
							error && el(Notice, { status: "error", isDismissible: false, style: { marginTop: "12px" } }, error)
						)
					),

					// Embed Card
					el("div", { style: cardStyle },
						el("div", { style: cardHeaderStyle }, __("Embed", "bb-forms-for-hubspot")),
						el("div", { style: cardBodyStyle },
							el("p", { style: { color: "#666", marginTop: 0 } },
								__("Use this shortcode to embed the form on any page or post.", "bb-forms-for-hubspot")
							),
							!postId 
								? el("p", { style: { fontStyle: "italic", color: "#666" } }, __("Save draft to generate shortcode.", "bb-forms-for-hubspot"))
								: el(Fragment, {},
									el(TextControl, {
										value: shortcode,
										readOnly: true,
										onFocus: function(e) { e.target.select(); },
										__nextHasNoMarginBottom: true,
									}),
									el(Button, {
										variant: "secondary",
										onClick: handleCopy,
										style: { marginTop: "8px" },
									}, copied ? __("Copied!", "bb-forms-for-hubspot") : __("Copy shortcode", "bb-forms-for-hubspot"))
								)
						)
					),

					// Fields Card (full width)
					el("div", { style: fullWidthCardStyle },
						el("div", { style: cardHeaderStyle }, __("Fields", "bb-forms-for-hubspot")),
						el("div", { style: cardBodyStyle },
							el("p", { style: { color: "#666", marginTop: 0 } },
								__("Reorder fields, rename labels, or hide fields. HubSpot property names and required rules are synced from HubSpot.", "bb-forms-for-hubspot")
							),
							!schemaFields.length
								? el("p", { style: { fontStyle: "italic", color: "#888", textAlign: "center", padding: "24px" } },
									__("Select a HubSpot form and sync to load fields.", "bb-forms-for-hubspot")
								)
								: el("div", {}, fieldRows)
						)
					)
				)
			);
		},

		save: function() {
			return null; // Dynamic block, no save output.
		},
	});
})();
