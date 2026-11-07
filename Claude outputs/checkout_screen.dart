import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../state/app_state.dart';
import '../../theme.dart';
import '../../utils/format.dart';
import '../../widgets/common.dart';
import '../sessions/session_flow.dart';
import 'payment_wait_screen.dart';

/// Pay for one session (GET sessions/{id}/checkout → POST sessions/{id}/pay).
/// Voucher first (a full-price voucher skips Mobile Money), then MTN / Airtel and the
/// number to charge. Starting the payment and confirming it run the website's own code.
class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key, required this.sessionId});

  final String sessionId;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  Map<String, dynamic>? _quote;
  Map<String, dynamic>? _session;
  ApiException? _error;
  String? _alreadyPaid;

  final _voucher = TextEditingController();
  final _phone = TextEditingController();
  String? _appliedCode;
  bool _applying = false;
  String _network = 'MTN';
  bool _paying = false;
  String? _payError;
  Map<String, String?> _fieldErrors = const {};

  @override
  void initState() {
    super.initState();
    _phone.text = context.read<AppState>().phoneDigits;
    _network = _guessNetwork(_phone.text) ?? 'MTN';
    _load();
  }

  @override
  void dispose() {
    _voucher.dispose();
    _phone.dispose();
    super.dispose();
  }

  /// MTN Uganda: 077/078/076/079 · Airtel: 070/074/075. Just a starting guess.
  String? _guessNetwork(String digits) {
    final local = digits.startsWith('256') ? '0${digits.substring(3)}' : digits;
    if (local.length < 3) return null;
    final p = local.substring(0, 3);
    if (['077', '078', '076', '079'].contains(p)) return 'MTN';
    if (['070', '074', '075'].contains(p)) return 'AIRTEL';
    return null;
  }

  Future<void> _load({String? voucher}) async {
    setState(() => _error = null);
    try {
      final data = (await context.read<AppState>().api.get(
        'sessions/${widget.sessionId}/checkout',
        query: voucher == null || voucher.isEmpty ? null : {'voucher': voucher},
      )) as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _quote = data['quote'] as Map<String, dynamic>;
        _session = data['session'] as Map<String, dynamic>;
        _appliedCode = _quote!['applied'] == true ? '${_quote!['code']}' : null;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.status == 409) {
        setState(() => _alreadyPaid = e.message);
      } else if (_quote != null) {
        showSnack(context, e.message, error: true); // e.g. lost connection while applying a voucher
      } else {
        setState(() => _error = e);
      }
    }
  }

  Future<void> _applyVoucher() async {
    final code = _voucher.text.trim();
    if (code.isEmpty) return;
    setState(() => _applying = true);
    await _load(voucher: code);
    if (mounted) setState(() => _applying = false);
  }

  Future<void> _removeVoucher() async {
    _voucher.clear();
    setState(() => _appliedCode = null);
    await _load();
  }

  Future<void> _pay() async {
    final free = _quote?['free'] == true;
    setState(() {
      _paying = true;
      _payError = null;
      _fieldErrors = const {};
    });
    final app = context.read<AppState>();
    try {
      final res = (await app.api.post('sessions/${widget.sessionId}/pay', {
        if (_appliedCode != null) 'voucher': _appliedCode,
        if (!free) 'network': _network,
        if (!free) 'phone_number': _phone.text.replaceAll(RegExp(r'[^0-9]'), ''),
      })) as Map<String, dynamic>;
      if (!mounted) return;
      await _after(res);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _payError = e.errors.isEmpty ? e.message : null;
        // A voucher that stopped working: drop it so the voucher box shows why.
        if (e.fieldError('voucher') != null) _appliedCode = null;
        _fieldErrors = {
          'phone_number': e.fieldError('phone_number'),
          'network': e.fieldError('network'),
          'voucher': e.fieldError('voucher'),
        };
      });
      if (e.status == 409) _load();
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  Future<void> _after(Map<String, dynamic> res) async {
    var result = res;
    if (res['state'] == 'pending') {
      final done = await Navigator.push<Map<String, dynamic>>(
        context,
        MaterialPageRoute(builder: (_) => PaymentWaitScreen(start: res)),
      );
      if (!mounted) return;
      if (done == null) {
        // Left the wait screen — the payment may still come through.
        context.read<AppState>().dataChanged();
        Navigator.pop(context);
        showSnack(context, "We'll keep your session. If the payment goes through it will show in My sessions.");
        return;
      }
      result = done;
    }

    final app = context.read<AppState>();
    if (result['state'] == 'succeeded') {
      app.dataChanged();
      showSnack(context, (result['message'] as String?) ?? 'Payment received. Thank you.');
      final session = result['session'];
      if (session is Map<String, dynamic>) {
        openSession(context, session, replace: true);
      } else {
        Navigator.pop(context);
      }
    } else {
      setState(() => _payError = (result['message'] as String?) ?? 'Payment was not completed. You can try again.');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pay for your session')),
      body: _alreadyPaid != null
          ? Center(
              child: EmptyState(
                icon: Icons.check_rounded,
                title: 'Nothing to pay',
                text: _alreadyPaid,
                actions: [
                  OutlinedButton(
                    onPressed: () => openSession(context, {'id': widget.sessionId, 'next_step': 'none'}, replace: true),
                    child: const Text('View session'),
                  ),
                ],
              ),
            )
          : _error != null && _quote == null
              ? ErrorView(message: _error!.message, onRetry: _load)
              : _quote == null
                  ? const LoadingView()
                  : _body(),
    );
  }

  Widget _body() {
    final q = _quote!;
    final s = _session ?? const {};
    final currency = '${q['currency'] ?? 'UGX'}';
    final free = q['free'] == true;
    final therapist = s['therapist'] as Map<String, dynamic>?;
    final at = parseDate(s['scheduled_at']);
    final networks = ((context.read<AppState>().config?['mobile_money_networks'] as List?) ??
            const [
              {'value': 'MTN', 'label': 'MTN Mobile Money'},
              {'value': 'AIRTEL', 'label': 'Airtel Money'},
            ])
        .cast<Map>();

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
      children: [
        MwCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  therapist != null
                      ? Avatar(name: '${therapist['name']}', size: 44, photoUrl: therapist['photo_url'] as String?)
                      : Container(
                          width: 44,
                          height: 44,
                          decoration: const BoxDecoration(color: Mw.tealTint, shape: BoxShape.circle),
                          child: const Icon(Icons.favorite_outline_rounded, color: Mw.teal),
                        ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(therapist != null ? 'Session with ${therapist['name']}' : 'Your session',
                            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15.5, color: Mw.ink900)),
                        Text(
                          at != null ? '${fmtDayTime(at)} · ${modeLine(s)}' : "${modeLine(s)} · we'll match you with a therapist",
                          style: const TextStyle(color: Mw.ink500, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 26),
              _line('Session', money(q['original'], currency)),
              if (q['applied'] == true) _line('Voucher ${q['code']}', '− ${money(q['discount'], currency)}', color: Mw.teal),
              const SizedBox(height: 4),
              _line('To pay', money(q['total'], currency), bold: true),
            ],
          ),
        ),

        // Voucher
        MwCard(
          child: _appliedCode != null
              ? Row(
                  children: [
                    const Icon(Icons.local_offer_rounded, color: Mw.teal),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text('Voucher $_appliedCode applied', style: const TextStyle(fontWeight: FontWeight.w800, color: Mw.tealDark)),
                    ),
                    TextButton(onPressed: _removeVoucher, child: const Text('Remove')),
                  ],
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Have a voucher?', style: TextStyle(fontWeight: FontWeight.w800, color: Mw.ink900)),
                    const SizedBox(height: 8),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _voucher,
                            textCapitalization: TextCapitalization.characters,
                            decoration: InputDecoration(
                              hintText: 'Voucher code',
                              errorText: (q['error'] as String?) ?? _fieldErrors['voucher'],
                            ),
                            onSubmitted: (_) => _applyVoucher(),
                          ),
                        ),
                        const SizedBox(width: 8),
                        SizedBox(
                          height: 50,
                          child: OutlinedButton(
                            onPressed: _applying ? null : _applyVoucher,
                            child: _applying
                                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                : const Text('Apply'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
        ),

        if (free)
          const NoticeBox(text: 'Your voucher covers the full price. There is nothing to pay.', error: false)
        else
          MwCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Pay with Mobile Money', style: TextStyle(fontWeight: FontWeight.w800, color: Mw.ink900)),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: SegmentedButton<String>(
                    segments: [
                      for (final n in networks) ButtonSegment(value: '${n['value']}', label: Text('${n['label']}')),
                    ],
                    selected: {_network},
                    showSelectedIcon: false,
                    onSelectionChanged: (v) => setState(() => _network = v.first),
                  ),
                ),
                if (_fieldErrors['network'] != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(_fieldErrors['network']!, style: const TextStyle(color: Mw.danger, fontSize: 12.5)),
                  ),
                const SizedBox(height: 14),
                TextField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    labelText: 'Mobile Money number',
                    hintText: 'e.g. 0772123456',
                    errorText: _fieldErrors['phone_number'],
                    prefixIcon: const Icon(Icons.phone_android_rounded),
                  ),
                  onChanged: (v) {
                    final g = _guessNetwork(v.replaceAll(RegExp(r'[^0-9]'), ''));
                    if (g != null && g != _network) setState(() => _network = g);
                  },
                ),
                const SizedBox(height: 8),
                const Text("You'll get a prompt on this phone to enter your PIN.",
                    style: TextStyle(color: Mw.ink500, fontSize: 12.5)),
              ],
            ),
          ),

        if (_payError != null) NoticeBox(text: _payError!),
        PrimaryButton(
          label: free ? 'Confirm' : 'Pay ${money(q['total'], currency)}',
          busy: _paying,
          onPressed: _pay,
        ),
        const SizedBox(height: 12),
        const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.lock_outline_rounded, size: 15, color: Mw.ink300),
            SizedBox(width: 6),
            Flexible(child: Text('Paid securely by Mobile Money. You approve it with your PIN.', textAlign: TextAlign.center, style: TextStyle(color: Mw.ink300, fontSize: 12))),
          ],
        ),
      ],
    );
  }

  Widget _line(String label, String value, {bool bold = false, Color? color}) {
    final style = TextStyle(
      fontSize: bold ? 17 : 14,
      fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
      color: color ?? (bold ? Mw.ink900 : Mw.ink700),
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(value, style: style),
        ],
      ),
    );
  }
}
