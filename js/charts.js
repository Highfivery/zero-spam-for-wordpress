(function(a) {
    var b = "0.3.4", c = "hasOwnProperty", d = /[\.\/]/, e = "*", f = function() {}, g = function(a, b) {
        return a - b;
    }, h, i, j = {
        n: {}
    }, k = function(a, b) {
        var c = j, d = i, e = Array.prototype.slice.call(arguments, 2), f = k.listeners(a), l = 0, m = !1, n, o = [], p = {}, q = [], r = h, s = [];
        h = a, i = 0;
        for (var t = 0, u = f.length; t < u; t++) "zIndex" in f[t] && (o.push(f[t].zIndex), 
        f[t].zIndex < 0 && (p[f[t].zIndex] = f[t]));
        o.sort(g);
        while (o[l] < 0) {
            n = p[o[l++]], q.push(n.apply(b, e));
            if (i) {
                i = d;
                return q;
            }
        }
        for (t = 0; t < u; t++) {
            n = f[t];
            if ("zIndex" in n) if (n.zIndex == o[l]) {
                q.push(n.apply(b, e));
                if (i) break;
                do {
                    l++, n = p[o[l]], n && q.push(n.apply(b, e));
                    if (i) break;
                } while (n);
            } else p[n.zIndex] = n; else {
                q.push(n.apply(b, e));
                if (i) break;
            }
        }
        i = d, h = r;
        return q.length ? q : null;
    };
    k.listeners = function(a) {
        var b = a.split(d), c = j, f, g, h, i, k, l, m, n, o = [ c ], p = [];
        for (i = 0, k = b.length; i < k; i++) {
            n = [];
            for (l = 0, m = o.length; l < m; l++) {
                c = o[l].n, g = [ c[b[i]], c[e] ], h = 2;
                while (h--) f = g[h], f && (n.push(f), p = p.concat(f.f || []));
            }
            o = n;
        }
        return p;
    }, k.on = function(a, b) {
        var c = a.split(d), e = j;
        for (var g = 0, h = c.length; g < h; g++) e = e.n, !e[c[g]] && (e[c[g]] = {
            n: {}
        }), e = e[c[g]];
        e.f = e.f || [];
        for (g = 0, h = e.f.length; g < h; g++) if (e.f[g] == b) return f;
        e.f.push(b);
        return function(a) {
            +a == +a && (b.zIndex = +a);
        };
    }, k.stop = function() {
        i = 1;
    }, k.nt = function(a) {
        if (a) return new RegExp("(?:\\.|\\/|^)" + a + "(?:\\.|\\/|$)").test(h);
        return h;
    }, k.off = k.unbind = function(a, b) {
        var f = a.split(d), g, h, i, k, l, m, n, o = [ j ];
        for (k = 0, l = f.length; k < l; k++) for (m = 0; m < o.length; m += i.length - 2) {
            i = [ m, 1 ], g = o[m].n;
            if (f[k] != e) g[f[k]] && i.push(g[f[k]]); else for (h in g) g[c](h) && i.push(g[h]);
            o.splice.apply(o, i);
        }
        for (k = 0, l = o.length; k < l; k++) {
            g = o[k];
            while (g.n) {
                if (b) {
                    if (g.f) {
                        for (m = 0, n = g.f.length; m < n; m++) if (g.f[m] == b) {
                            g.f.splice(m, 1);
                            break;
                        }
                        !g.f.length && delete g.f;
                    }
                    for (h in g.n) if (g.n[c](h) && g.n[h].f) {
                        var p = g.n[h].f;
                        for (m = 0, n = p.length; m < n; m++) if (p[m] == b) {
                            p.splice(m, 1);
                            break;
                        }
                        !p.length && delete g.n[h].f;
                    }
                } else {
                    delete g.f;
                    for (h in g.n) g.n[c](h) && g.n[h].f && delete g.n[h].f;
                }
                g = g.n;
            }
        }
    }, k.once = function(a, b) {
        var c = function() {
            var d = b.apply(this, arguments);
            k.unbind(a, c);
            return d;
        };
        return k.on(a, c);
    }, k.version = b, k.toString = function() {
        return "You are running Eve " + b;
    }, typeof module != "undefined" && module.exports ? module.exports = k : typeof define != "undefined" ? define("eve", [], function() {
        return k;
    }) : a.eve = k;
})(this), function() {
    function cF(a) {
        for (var b = 0; b < cy.length; b++) cy[b].el.paper == a && cy.splice(b--, 1);
    }
    function cE(b, d, e, f, h, i) {
        e = Q(e);
        var j, k, l, m = [], o, p, q, t = b.ms, u = {}, v = {}, w = {};
        if (f) for (y = 0, z = cy.length; y < z; y++) {
            var x = cy[y];
            if (x.el.id == d.id && x.anim == b) {
                x.percent != e ? (cy.splice(y, 1), l = 1) : k = x, d.attr(x.totalOrigin);
                break;
            }
        } else f = +v;
        for (var y = 0, z = b.percents.length; y < z; y++) {
            if (b.percents[y] == e || b.percents[y] > f * b.top) {
                e = b.percents[y], p = b.percents[y - 1] || 0, t = t / b.top * (e - p), o = b.percents[y + 1], 
                j = b.anim[e];
                break;
            }
            f && d.attr(b.anim[b.percents[y]]);
        }
        if (!!j) {
            if (!k) {
                for (var A in j) if (j[g](A)) if (U[g](A) || d.paper.customAttributes[g](A)) {
                    u[A] = d.attr(A), u[A] == null && (u[A] = T[A]), v[A] = j[A];
                    switch (U[A]) {
                      case C:
                        w[A] = (v[A] - u[A]) / t;
                        break;

                      case "colour":
                        u[A] = a.getRGB(u[A]);
                        var B = a.getRGB(v[A]);
                        w[A] = {
                            r: (B.r - u[A].r) / t,
                            g: (B.g - u[A].g) / t,
                            b: (B.b - u[A].b) / t
                        };
                        break;

                      case "path":
                        var D = bR(u[A], v[A]), E = D[1];
                        u[A] = D[0], w[A] = [];
                        for (y = 0, z = u[A].length; y < z; y++) {
                            w[A][y] = [ 0 ];
                            for (var F = 1, G = u[A][y].length; F < G; F++) w[A][y][F] = (E[y][F] - u[A][y][F]) / t;
                        }
                        break;

                      case "transform":
                        var H = d._, I = ca(H[A], v[A]);
                        if (I) {
                            u[A] = I.from, v[A] = I.to, w[A] = [], w[A].real = !0;
                            for (y = 0, z = u[A].length; y < z; y++) {
                                w[A][y] = [ u[A][y][0] ];
                                for (F = 1, G = u[A][y].length; F < G; F++) w[A][y][F] = (v[A][y][F] - u[A][y][F]) / t;
                            }
                        } else {
                            var J = d.matrix || new cb(), K = {
                                _: {
                                    transform: H.transform
                                },
                                getBBox: function() {
                                    return d.getBBox(1);
                                }
                            };
                            u[A] = [ J.a, J.b, J.c, J.d, J.e, J.f ], b$(K, v[A]), v[A] = K._.transform, w[A] = [ (K.matrix.a - J.a) / t, (K.matrix.b - J.b) / t, (K.matrix.c - J.c) / t, (K.matrix.d - J.d) / t, (K.matrix.e - J.e) / t, (K.matrix.f - J.f) / t ];
                        }
                        break;

                      case "csv":
                        var L = r(j[A])[s](c), M = r(u[A])[s](c);
                        if (A == "clip-rect") {
                            u[A] = M, w[A] = [], y = M.length;
                            while (y--) w[A][y] = (L[y] - u[A][y]) / t;
                        }
                        v[A] = L;
                        break;

                      default:
                        L = [][n](j[A]), M = [][n](u[A]), w[A] = [], y = d.paper.customAttributes[A].length;
                        while (y--) w[A][y] = ((L[y] || 0) - (M[y] || 0)) / t;
                    }
                }
                var O = j.easing, P = a.easing_formulas[O];
                if (!P) {
                    P = r(O).match(N);
                    if (P && P.length == 5) {
                        var R = P;
                        P = function(a) {
                            return cC(a, +R[1], +R[2], +R[3], +R[4], t);
                        };
                    } else P = bf;
                }
                q = j.start || b.start || +new Date(), x = {
                    anim: b,
                    percent: e,
                    timestamp: q,
                    start: q + (b.del || 0),
                    status: 0,
                    initstatus: f || 0,
                    stop: !1,
                    ms: t,
                    easing: P,
                    from: u,
                    diff: w,
                    to: v,
                    el: d,
                    callback: j.callback,
                    prev: p,
                    next: o,
                    repeat: i || b.times,
                    origin: d.attr(),
                    totalOrigin: h
                }, cy.push(x);
                if (f && !k && !l) {
                    x.stop = !0, x.start = new Date() - t * f;
                    if (cy.length == 1) return cA();
                }
                l && (x.start = new Date() - x.ms * f), cy.length == 1 && cz(cA);
            } else k.initstatus = f, k.start = new Date() - k.ms * f;
            eve("raphael.anim.start." + d.id, d, b);
        }
    }
    function cD(a, b) {
        var c = [], d = {};
        this.ms = b, this.times = 1;
        if (a) {
            for (var e in a) a[g](e) && (d[Q(e)] = a[e], c.push(Q(e)));
            c.sort(bd);
        }
        this.anim = d, this.top = c[c.length - 1], this.percents = c;
    }
    function cC(a, b, c, d, e, f) {
        function o(a, b) {
            var c, d, e, f, j, k;
            for (e = a, k = 0; k < 8; k++) {
                f = m(e) - a;
                if (z(f) < b) return e;
                j = (3 * i * e + 2 * h) * e + g;
                if (z(j) < 1e-6) break;
                e = e - f / j;
            }
            c = 0, d = 1, e = a;
            if (e < c) return c;
            if (e > d) return d;
            while (c < d) {
                f = m(e);
                if (z(f - a) < b) return e;
                a > f ? c = e : d = e, e = (d - c) / 2 + c;
            }
            return e;
        }
        function n(a, b) {
            var c = o(a, b);
            return ((l * c + k) * c + j) * c;
        }
        function m(a) {
            return ((i * a + h) * a + g) * a;
        }
        var g = 3 * b, h = 3 * (d - b) - g, i = 1 - g - h, j = 3 * c, k = 3 * (e - c) - j, l = 1 - j - k;
        return n(a, 1 / (200 * f));
    }
    function cq() {
        return this.x + q + this.y + q + this.width + " × " + this.height;
    }
    function cp() {
        return this.x + q + this.y;
    }
    function cb(a, b, c, d, e, f) {
        a != null ? (this.a = +a, this.b = +b, this.c = +c, this.d = +d, this.e = +e, this.f = +f) : (this.a = 1, 
        this.b = 0, this.c = 0, this.d = 1, this.e = 0, this.f = 0);
    }
    function bH(b, c, d) {
        b = a._path2curve(b), c = a._path2curve(c);
        var e, f, g, h, i, j, k, l, m, n, o = d ? 0 : [];
        for (var p = 0, q = b.length; p < q; p++) {
            var r = b[p];
            if (r[0] == "M") e = i = r[1], f = j = r[2]; else {
                r[0] == "C" ? (m = [ e, f ].concat(r.slice(1)), e = m[6], f = m[7]) : (m = [ e, f, e, f, i, j, i, j ], 
                e = i, f = j);
                for (var s = 0, t = c.length; s < t; s++) {
                    var u = c[s];
                    if (u[0] == "M") g = k = u[1], h = l = u[2]; else {
                        u[0] == "C" ? (n = [ g, h ].concat(u.slice(1)), g = n[6], h = n[7]) : (n = [ g, h, g, h, k, l, k, l ], 
                        g = k, h = l);
                        var v = bG(m, n, d);
                        if (d) o += v; else {
                            for (var w = 0, x = v.length; w < x; w++) v[w].segment1 = p, v[w].segment2 = s, 
                            v[w].bez1 = m, v[w].bez2 = n;
                            o = o.concat(v);
                        }
                    }
                }
            }
        }
        return o;
    }
    function bG(b, c, d) {
        var e = a.bezierBBox(b), f = a.bezierBBox(c);
        if (!a.isBBoxIntersect(e, f)) return d ? 0 : [];
        var g = bB.apply(0, b), h = bB.apply(0, c), i = ~~(g / 5), j = ~~(h / 5), k = [], l = [], m = {}, n = d ? 0 : [];
        for (var o = 0; o < i + 1; o++) {
            var p = a.findDotsAtSegment.apply(a, b.concat(o / i));
            k.push({
                x: p.x,
                y: p.y,
                t: o / i
            });
        }
        for (o = 0; o < j + 1; o++) p = a.findDotsAtSegment.apply(a, c.concat(o / j)), l.push({
            x: p.x,
            y: p.y,
            t: o / j
        });
        for (o = 0; o < i; o++) for (var q = 0; q < j; q++) {
            var r = k[o], s = k[o + 1], t = l[q], u = l[q + 1], v = z(s.x - r.x) < .001 ? "y" : "x", w = z(u.x - t.x) < .001 ? "y" : "x", x = bD(r.x, r.y, s.x, s.y, t.x, t.y, u.x, u.y);
            if (x) {
                if (m[x.x.toFixed(4)] == x.y.toFixed(4)) continue;
                m[x.x.toFixed(4)] = x.y.toFixed(4);
                var y = r.t + z((x[v] - r[v]) / (s[v] - r[v])) * (s.t - r.t), A = t.t + z((x[w] - t[w]) / (u[w] - t[w])) * (u.t - t.t);
                y >= 0 && y <= 1 && A >= 0 && A <= 1 && (d ? n++ : n.push({
                    x: x.x,
                    y: x.y,
                    t1: y,
                    t2: A
                }));
            }
        }
        return n;
    }
    function bF(a, b) {
        return bG(a, b, 1);
    }
    function bE(a, b) {
        return bG(a, b);
    }
    function bD(a, b, c, d, e, f, g, h) {
        if (!(x(a, c) < y(e, g) || y(a, c) > x(e, g) || x(b, d) < y(f, h) || y(b, d) > x(f, h))) {
            var i = (a * d - b * c) * (e - g) - (a - c) * (e * h - f * g), j = (a * d - b * c) * (f - h) - (b - d) * (e * h - f * g), k = (a - c) * (f - h) - (b - d) * (e - g);
            if (!k) return;
            var l = i / k, m = j / k, n = +l.toFixed(2), o = +m.toFixed(2);
            if (n < +y(a, c).toFixed(2) || n > +x(a, c).toFixed(2) || n < +y(e, g).toFixed(2) || n > +x(e, g).toFixed(2) || o < +y(b, d).toFixed(2) || o > +x(b, d).toFixed(2) || o < +y(f, h).toFixed(2) || o > +x(f, h).toFixed(2)) return;
            return {
                x: l,
                y: m
            };
        }
    }
    function bC(a, b, c, d, e, f, g, h, i) {
        if (!(i < 0 || bB(a, b, c, d, e, f, g, h) < i)) {
            var j = 1, k = j / 2, l = j - k, m, n = .01;
            m = bB(a, b, c, d, e, f, g, h, l);
            while (z(m - i) > n) k /= 2, l += (m < i ? 1 : -1) * k, m = bB(a, b, c, d, e, f, g, h, l);
            return l;
        }
    }
    function bB(a, b, c, d, e, f, g, h, i) {
        i == null && (i = 1), i = i > 1 ? 1 : i < 0 ? 0 : i;
        var j = i / 2, k = 12, l = [ -.1252, .1252, -.3678, .3678, -.5873, .5873, -.7699, .7699, -.9041, .9041, -.9816, .9816 ], m = [ .2491, .2491, .2335, .2335, .2032, .2032, .1601, .1601, .1069, .1069, .0472, .0472 ], n = 0;
        for (var o = 0; o < k; o++) {
            var p = j * l[o] + j, q = bA(p, a, c, e, g), r = bA(p, b, d, f, h), s = q * q + r * r;
            n += m[o] * w.sqrt(s);
        }
        return j * n;
    }
    function bA(a, b, c, d, e) {
        var f = -3 * b + 9 * c - 9 * d + 3 * e, g = a * f + 6 * b - 12 * c + 6 * d;
        return a * g - 3 * b + 3 * c;
    }
    function by(a, b) {
        var c = [];
        for (var d = 0, e = a.length; e - 2 * !b > d; d += 2) {
            var f = [ {
                x: +a[d - 2],
                y: +a[d - 1]
            }, {
                x: +a[d],
                y: +a[d + 1]
            }, {
                x: +a[d + 2],
                y: +a[d + 3]
            }, {
                x: +a[d + 4],
                y: +a[d + 5]
            } ];
            b ? d ? e - 4 == d ? f[3] = {
                x: +a[0],
                y: +a[1]
            } : e - 2 == d && (f[2] = {
                x: +a[0],
                y: +a[1]
            }, f[3] = {
                x: +a[2],
                y: +a[3]
            }) : f[0] = {
                x: +a[e - 2],
                y: +a[e - 1]
            } : e - 4 == d ? f[3] = f[2] : d || (f[0] = {
                x: +a[d],
                y: +a[d + 1]
            }), c.push([ "C", (-f[0].x + 6 * f[1].x + f[2].x) / 6, (-f[0].y + 6 * f[1].y + f[2].y) / 6, (f[1].x + 6 * f[2].x - f[3].x) / 6, (f[1].y + 6 * f[2].y - f[3].y) / 6, f[2].x, f[2].y ]);
        }
        return c;
    }
    function bx() {
        return this.hex;
    }
    function bv(a, b, c) {
        function d() {
            var e = Array.prototype.slice.call(arguments, 0), f = e.join("␀"), h = d.cache = d.cache || {}, i = d.count = d.count || [];
            if (h[g](f)) {
                bu(i, f);
                return c ? c(h[f]) : h[f];
            }
            i.length >= 1e3 && delete h[i.shift()], i.push(f), h[f] = a[m](b, e);
            return c ? c(h[f]) : h[f];
        }
        return d;
    }
    function bu(a, b) {
        for (var c = 0, d = a.length; c < d; c++) if (a[c] === b) return a.push(a.splice(c, 1)[0]);
    }
    function bm(a) {
        if (Object(a) !== a) return a;
        var b = new a.constructor();
        for (var c in a) a[g](c) && (b[c] = bm(a[c]));
        return b;
    }
    function a(c) {
        if (a.is(c, "function")) return b ? c() : eve.on("raphael.DOMload", c);
        if (a.is(c, E)) return a._engine.create[m](a, c.splice(0, 3 + a.is(c[0], C))).add(c);
        var d = Array.prototype.slice.call(arguments, 0);
        if (a.is(d[d.length - 1], "function")) {
            var e = d.pop();
            return b ? e.call(a._engine.create[m](a, d)) : eve.on("raphael.DOMload", function() {
                e.call(a._engine.create[m](a, d));
            });
        }
        return a._engine.create[m](a, arguments);
    }
    a.version = "2.1.0", a.eve = eve;
    var b, c = /[, ]+/, d = {
        circle: 1,
        rect: 1,
        path: 1,
        ellipse: 1,
        text: 1,
        image: 1
    }, e = /\{(\d+)\}/g, f = "prototype", g = "hasOwnProperty", h = {
        doc: document,
        win: window
    }, i = {
        was: Object.prototype[g].call(h.win, "Raphael"),
        is: h.win.Raphael
    }, j = function() {
        this.ca = this.customAttributes = {};
    }, k, l = "appendChild", m = "apply", n = "concat", o = "createTouch" in h.doc, p = "", q = " ", r = String, s = "split", t = "click dblclick mousedown mousemove mouseout mouseover mouseup touchstart touchmove touchend touchcancel"[s](q), u = {
        mousedown: "touchstart",
        mousemove: "touchmove",
        mouseup: "touchend"
    }, v = r.prototype.toLowerCase, w = Math, x = w.max, y = w.min, z = w.abs, A = w.pow, B = w.PI, C = "number", D = "string", E = "array", F = "toString", G = "fill", H = Object.prototype.toString, I = {}, J = "push", K = a._ISURL = /^url\(['"]?([^\)]+?)['"]?\)$/i, L = /^\s*((#[a-f\d]{6})|(#[a-f\d]{3})|rgba?\(\s*([\d\.]+%?\s*,\s*[\d\.]+%?\s*,\s*[\d\.]+%?(?:\s*,\s*[\d\.]+%?)?)\s*\)|hsba?\(\s*([\d\.]+(?:deg|\xb0|%)?\s*,\s*[\d\.]+%?\s*,\s*[\d\.]+(?:%?\s*,\s*[\d\.]+)?)%?\s*\)|hsla?\(\s*([\d\.]+(?:deg|\xb0|%)?\s*,\s*[\d\.]+%?\s*,\s*[\d\.]+(?:%?\s*,\s*[\d\.]+)?)%?\s*\))\s*$/i, M = {
        NaN: 1,
        Infinity: 1,
        "-Infinity": 1
    }, N = /^(?:cubic-)?bezier\(([^,]+),([^,]+),([^,]+),([^\)]+)\)/, O = w.round, P = "setAttribute", Q = parseFloat, R = parseInt, S = r.prototype.toUpperCase, T = a._availableAttrs = {
        "arrow-end": "none",
        "arrow-start": "none",
        blur: 0,
        "clip-rect": "0 0 1e9 1e9",
        cursor: "default",
        cx: 0,
        cy: 0,
        fill: "#fff",
        "fill-opacity": 1,
        font: '10px "Arial"',
        "font-family": '"Arial"',
        "font-size": "10",
        "font-style": "normal",
        "font-weight": 400,
        gradient: 0,
        height: 0,
        href: "http://raphaeljs.com/",
        "letter-spacing": 0,
        opacity: 1,
        path: "M0,0",
        r: 0,
        rx: 0,
        ry: 0,
        src: "",
        stroke: "#000",
        "stroke-dasharray": "",
        "stroke-linecap": "butt",
        "stroke-linejoin": "butt",
        "stroke-miterlimit": 0,
        "stroke-opacity": 1,
        "stroke-width": 1,
        target: "_blank",
        "text-anchor": "middle",
        title: "Raphael",
        transform: "",
        width: 0,
        x: 0,
        y: 0
    }, U = a._availableAnimAttrs = {
        blur: C,
        "clip-rect": "csv",
        cx: C,
        cy: C,
        fill: "colour",
        "fill-opacity": C,
        "font-size": C,
        height: C,
        opacity: C,
        path: "path",
        r: C,
        rx: C,
        ry: C,
        stroke: "colour",
        "stroke-opacity": C,
        "stroke-width": C,
        transform: "transform",
        width: C,
        x: C,
        y: C
    }, V = /[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]/g, W = /[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*,[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*/, X = {
        hs: 1,
        rg: 1
    }, Y = /,?([achlmqrstvxz]),?/gi, Z = /([achlmrqstvz])[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029,]*((-?\d*\.?\d*(?:e[\-+]?\d+)?[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*,?[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*)+)/gi, $ = /([rstm])[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029,]*((-?\d*\.?\d*(?:e[\-+]?\d+)?[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*,?[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*)+)/gi, _ = /(-?\d*\.?\d*(?:e[\-+]?\d+)?)[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*,?[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*/gi, ba = a._radial_gradient = /^r(?:\(([^,]+?)[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*,[\x09\x0a\x0b\x0c\x0d\x20\xa0\u1680\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\u2028\u2029]*([^\)]+?)\))?/, bb = {}, bc = function(a, b) {
        return a.key - b.key;
    }, bd = function(a, b) {
        return Q(a) - Q(b);
    }, be = function() {}, bf = function(a) {
        return a;
    }, bg = a._rectPath = function(a, b, c, d, e) {
        if (e) return [ [ "M", a + e, b ], [ "l", c - e * 2, 0 ], [ "a", e, e, 0, 0, 1, e, e ], [ "l", 0, d - e * 2 ], [ "a", e, e, 0, 0, 1, -e, e ], [ "l", e * 2 - c, 0 ], [ "a", e, e, 0, 0, 1, -e, -e ], [ "l", 0, e * 2 - d ], [ "a", e, e, 0, 0, 1, e, -e ], [ "z" ] ];
        return [ [ "M", a, b ], [ "l", c, 0 ], [ "l", 0, d ], [ "l", -c, 0 ], [ "z" ] ];
    }, bh = function(a, b, c, d) {
        d == null && (d = c);
        return [ [ "M", a, b ], [ "m", 0, -d ], [ "a", c, d, 0, 1, 1, 0, 2 * d ], [ "a", c, d, 0, 1, 1, 0, -2 * d ], [ "z" ] ];
    }, bi = a._getPath = {
        path: function(a) {
            return a.attr("path");
        },
        circle: function(a) {
            var b = a.attrs;
            return bh(b.cx, b.cy, b.r);
        },
        ellipse: function(a) {
            var b = a.attrs;
            return bh(b.cx, b.cy, b.rx, b.ry);
        },
        rect: function(a) {
            var b = a.attrs;
            return bg(b.x, b.y, b.width, b.height, b.r);
        },
        image: function(a) {
            var b = a.attrs;
            return bg(b.x, b.y, b.width, b.height);
        },
        text: function(a) {
            var b = a._getBBox();
            return bg(b.x, b.y, b.width, b.height);
        }
    }, bj = a.mapPath = function(a, b) {
        if (!b) return a;
        var c, d, e, f, g, h, i;
        a = bR(a);
        for (e = 0, g = a.length; e < g; e++) {
            i = a[e];
            for (f = 1, h = i.length; f < h; f += 2) c = b.x(i[f], i[f + 1]), d = b.y(i[f], i[f + 1]), 
            i[f] = c, i[f + 1] = d;
        }
        return a;
    };
    a._g = h, a.type = h.win.SVGAngle || h.doc.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#BasicStructure", "1.1") ? "SVG" : "VML";
    if (a.type == "VML") {
        var bk = h.doc.createElement("div"), bl;
        bk.innerHTML = '<v:shape adj="1"/>', bl = bk.firstChild, bl.style.behavior = "url(#default#VML)";
        if (!bl || typeof bl.adj != "object") return a.type = p;
        bk = null;
    }
    a.svg = !(a.vml = a.type == "VML"), a._Paper = j, a.fn = k = j.prototype = a.prototype, 
    a._id = 0, a._oid = 0, a.is = function(a, b) {
        b = v.call(b);
        if (b == "finite") return !M[g](+a);
        if (b == "array") return a instanceof Array;
        return b == "null" && a === null || b == typeof a && a !== null || b == "object" && a === Object(a) || b == "array" && Array.isArray && Array.isArray(a) || H.call(a).slice(8, -1).toLowerCase() == b;
    }, a.angle = function(b, c, d, e, f, g) {
        if (f == null) {
            var h = b - d, i = c - e;
            if (!h && !i) return 0;
            return (180 + w.atan2(-i, -h) * 180 / B + 360) % 360;
        }
        return a.angle(b, c, f, g) - a.angle(d, e, f, g);
    }, a.rad = function(a) {
        return a % 360 * B / 180;
    }, a.deg = function(a) {
        return a * 180 / B % 360;
    }, a.snapTo = function(b, c, d) {
        d = a.is(d, "finite") ? d : 10;
        if (a.is(b, E)) {
            var e = b.length;
            while (e--) if (z(b[e] - c) <= d) return b[e];
        } else {
            b = +b;
            var f = c % b;
            if (f < d) return c - f;
            if (f > b - d) return c - f + b;
        }
        return c;
    };
    var bn = a.createUUID = function(a, b) {
        return function() {
            return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(a, b).toUpperCase();
        };
    }(/[xy]/g, function(a) {
        var b = w.random() * 16 | 0, c = a == "x" ? b : b & 3 | 8;
        return c.toString(16);
    });
    a.setWindow = function(b) {
        eve("raphael.setWindow", a, h.win, b), h.win = b, h.doc = h.win.document, a._engine.initWin && a._engine.initWin(h.win);
    };
    var bo = function(b) {
        if (a.vml) {
            var c = /^\s+|\s+$/g, d;
            try {
                var e = new ActiveXObject("htmlfile");
                e.write("<body>"), e.close(), d = e.body;
            } catch (f) {
                d = createPopup().document.body;
            }
            var g = d.createTextRange();
            bo = bv(function(a) {
                try {
                    d.style.color = r(a).replace(c, p);
                    var b = g.queryCommandValue("ForeColor");
                    b = (b & 255) << 16 | b & 65280 | (b & 16711680) >>> 16;
                    return "#" + ("000000" + b.toString(16)).slice(-6);
                } catch (e) {
                    return "none";
                }
            });
        } else {
            var i = h.doc.createElement("i");
            i.title = "Raphaël Colour Picker", i.style.display = "none", h.doc.body.appendChild(i), 
            bo = bv(function(a) {
                i.style.color = a;
                return h.doc.defaultView.getComputedStyle(i, p).getPropertyValue("color");
            });
        }
        return bo(b);
    }, bp = function() {
        return "hsb(" + [ this.h, this.s, this.b ] + ")";
    }, bq = function() {
        return "hsl(" + [ this.h, this.s, this.l ] + ")";
    }, br = function() {
        return this.hex;
    }, bs = function(b, c, d) {
        c == null && a.is(b, "object") && "r" in b && "g" in b && "b" in b && (d = b.b, 
        c = b.g, b = b.r);
        if (c == null && a.is(b, D)) {
            var e = a.getRGB(b);
            b = e.r, c = e.g, d = e.b;
        }
        if (b > 1 || c > 1 || d > 1) b /= 255, c /= 255, d /= 255;
        return [ b, c, d ];
    }, bt = function(b, c, d, e) {
        b *= 255, c *= 255, d *= 255;
        var f = {
            r: b,
            g: c,
            b: d,
            hex: a.rgb(b, c, d),
            toString: br
        };
        a.is(e, "finite") && (f.opacity = e);
        return f;
    };
    a.color = function(b) {
        var c;
        a.is(b, "object") && "h" in b && "s" in b && "b" in b ? (c = a.hsb2rgb(b), b.r = c.r, 
        b.g = c.g, b.b = c.b, b.hex = c.hex) : a.is(b, "object") && "h" in b && "s" in b && "l" in b ? (c = a.hsl2rgb(b), 
        b.r = c.r, b.g = c.g, b.b = c.b, b.hex = c.hex) : (a.is(b, "string") && (b = a.getRGB(b)), 
        a.is(b, "object") && "r" in b && "g" in b && "b" in b ? (c = a.rgb2hsl(b), b.h = c.h, 
        b.s = c.s, b.l = c.l, c = a.rgb2hsb(b), b.v = c.b) : (b = {
            hex: "none"
        }, b.r = b.g = b.b = b.h = b.s = b.v = b.l = -1)), b.toString = br;
        return b;
    }, a.hsb2rgb = function(a, b, c, d) {
        this.is(a, "object") && "h" in a && "s" in a && "b" in a && (c = a.b, b = a.s, a = a.h, 
        d = a.o), a *= 360;
        var e, f, g, h, i;
        a = a % 360 / 60, i = c * b, h = i * (1 - z(a % 2 - 1)), e = f = g = c - i, a = ~~a, 
        e += [ i, h, 0, 0, h, i ][a], f += [ h, i, i, h, 0, 0 ][a], g += [ 0, 0, h, i, i, h ][a];
        return bt(e, f, g, d);
    }, a.hsl2rgb = function(a, b, c, d) {
        this.is(a, "object") && "h" in a && "s" in a && "l" in a && (c = a.l, b = a.s, a = a.h);
        if (a > 1 || b > 1 || c > 1) a /= 360, b /= 100, c /= 100;
        a *= 360;
        var e, f, g, h, i;
        a = a % 360 / 60, i = 2 * b * (c < .5 ? c : 1 - c), h = i * (1 - z(a % 2 - 1)), 
        e = f = g = c - i / 2, a = ~~a, e += [ i, h, 0, 0, h, i ][a], f += [ h, i, i, h, 0, 0 ][a], 
        g += [ 0, 0, h, i, i, h ][a];
        return bt(e, f, g, d);
    }, a.rgb2hsb = function(a, b, c) {
        c = bs(a, b, c), a = c[0], b = c[1], c = c[2];
        var d, e, f, g;
        f = x(a, b, c), g = f - y(a, b, c), d = g == 0 ? null : f == a ? (b - c) / g : f == b ? (c - a) / g + 2 : (a - b) / g + 4, 
        d = (d + 360) % 6 * 60 / 360, e = g == 0 ? 0 : g / f;
        return {
            h: d,
            s: e,
            b: f,
            toString: bp
        };
    }, a.rgb2hsl = function(a, b, c) {
        c = bs(a, b, c), a = c[0], b = c[1], c = c[2];
        var d, e, f, g, h, i;
        g = x(a, b, c), h = y(a, b, c), i = g - h, d = i == 0 ? null : g == a ? (b - c) / i : g == b ? (c - a) / i + 2 : (a - b) / i + 4, 
        d = (d + 360) % 6 * 60 / 360, f = (g + h) / 2, e = i == 0 ? 0 : f < .5 ? i / (2 * f) : i / (2 - 2 * f);
        return {
            h: d,
            s: e,
            l: f,
            toString: bq
        };
    }, a._path2string = function() {
        return this.join(",").replace(Y, "$1");
    };
    var bw = a._preload = function(a, b) {
        var c = h.doc.createElement("img");
        c.style.cssText = "position:absolute;left:-9999em;top:-9999em", c.onload = function() {
            b.call(this), this.onload = null, h.doc.body.removeChild(this);
        }, c.onerror = function() {
            h.doc.body.removeChild(this);
        }, h.doc.body.appendChild(c), c.src = a;
    };
    a.getRGB = bv(function(b) {
        if (!b || !!((b = r(b)).indexOf("-") + 1)) return {
            r: -1,
            g: -1,
            b: -1,
            hex: "none",
            error: 1,
            toString: bx
        };
        if (b == "none") return {
            r: -1,
            g: -1,
            b: -1,
            hex: "none",
            toString: bx
        };
        !X[g](b.toLowerCase().substring(0, 2)) && b.charAt() != "#" && (b = bo(b));
        var c, d, e, f, h, i, j, k = b.match(L);
        if (k) {
            k[2] && (f = R(k[2].substring(5), 16), e = R(k[2].substring(3, 5), 16), d = R(k[2].substring(1, 3), 16)), 
            k[3] && (f = R((i = k[3].charAt(3)) + i, 16), e = R((i = k[3].charAt(2)) + i, 16), 
            d = R((i = k[3].charAt(1)) + i, 16)), k[4] && (j = k[4][s](W), d = Q(j[0]), j[0].slice(-1) == "%" && (d *= 2.55), 
            e = Q(j[1]), j[1].slice(-1) == "%" && (e *= 2.55), f = Q(j[2]), j[2].slice(-1) == "%" && (f *= 2.55), 
            k[1].toLowerCase().slice(0, 4) == "rgba" && (h = Q(j[3])), j[3] && j[3].slice(-1) == "%" && (h /= 100));
            if (k[5]) {
                j = k[5][s](W), d = Q(j[0]), j[0].slice(-1) == "%" && (d *= 2.55), e = Q(j[1]), 
                j[1].slice(-1) == "%" && (e *= 2.55), f = Q(j[2]), j[2].slice(-1) == "%" && (f *= 2.55), 
                (j[0].slice(-3) == "deg" || j[0].slice(-1) == "°") && (d /= 360), k[1].toLowerCase().slice(0, 4) == "hsba" && (h = Q(j[3])), 
                j[3] && j[3].slice(-1) == "%" && (h /= 100);
                return a.hsb2rgb(d, e, f, h);
            }
            if (k[6]) {
                j = k[6][s](W), d = Q(j[0]), j[0].slice(-1) == "%" && (d *= 2.55), e = Q(j[1]), 
                j[1].slice(-1) == "%" && (e *= 2.55), f = Q(j[2]), j[2].slice(-1) == "%" && (f *= 2.55), 
                (j[0].slice(-3) == "deg" || j[0].slice(-1) == "°") && (d /= 360), k[1].toLowerCase().slice(0, 4) == "hsla" && (h = Q(j[3])), 
                j[3] && j[3].slice(-1) == "%" && (h /= 100);
                return a.hsl2rgb(d, e, f, h);
            }
            k = {
                r: d,
                g: e,
                b: f,
                toString: bx
            }, k.hex = "#" + (16777216 | f | e << 8 | d << 16).toString(16).slice(1), a.is(h, "finite") && (k.opacity = h);
            return k;
        }
        return {
            r: -1,
            g: -1,
            b: -1,
            hex: "none",
            error: 1,
            toString: bx
        };
    }, a), a.hsb = bv(function(b, c, d) {
        return a.hsb2rgb(b, c, d).hex;
    }), a.hsl = bv(function(b, c, d) {
        return a.hsl2rgb(b, c, d).hex;
    }), a.rgb = bv(function(a, b, c) {
        return "#" + (16777216 | c | b << 8 | a << 16).toString(16).slice(1);
    }), a.getColor = function(a) {
        var b = this.getColor.start = this.getColor.start || {
            h: 0,
            s: 1,
            b: a || .75
        }, c = this.hsb2rgb(b.h, b.s, b.b);
        b.h += .075, b.h > 1 && (b.h = 0, b.s -= .2, b.s <= 0 && (this.getColor.start = {
            h: 0,
            s: 1,
            b: b.b
        }));
        return c.hex;
    }, a.getColor.reset = function() {
        delete this.start;
    }, a.parsePathString = function(b) {
        if (!b) return null;
        var c = bz(b);
        if (c.arr) return bJ(c.arr);
        var d = {
            a: 7,
            c: 6,
            h: 1,
            l: 2,
            m: 2,
            r: 4,
            q: 4,
            s: 4,
            t: 2,
            v: 1,
            z: 0
        }, e = [];
        a.is(b, E) && a.is(b[0], E) && (e = bJ(b)), e.length || r(b).replace(Z, function(a, b, c) {
            var f = [], g = b.toLowerCase();
            c.replace(_, function(a, b) {
                b && f.push(+b);
            }), g == "m" && f.length > 2 && (e.push([ b ][n](f.splice(0, 2))), g = "l", b = b == "m" ? "l" : "L");
            if (g == "r") e.push([ b ][n](f)); else while (f.length >= d[g]) {
                e.push([ b ][n](f.splice(0, d[g])));
                if (!d[g]) break;
            }
        }), e.toString = a._path2string, c.arr = bJ(e);
        return e;
    }, a.parseTransformString = bv(function(b) {
        if (!b) return null;
        var c = {
            r: 3,
            s: 4,
            t: 2,
            m: 6
        }, d = [];
        a.is(b, E) && a.is(b[0], E) && (d = bJ(b)), d.length || r(b).replace($, function(a, b, c) {
            var e = [], f = v.call(b);
            c.replace(_, function(a, b) {
                b && e.push(+b);
            }), d.push([ b ][n](e));
        }), d.toString = a._path2string;
        return d;
    });
    var bz = function(a) {
        var b = bz.ps = bz.ps || {};
        b[a] ? b[a].sleep = 100 : b[a] = {
            sleep: 100
        }, setTimeout(function() {
            for (var c in b) b[g](c) && c != a && (b[c].sleep--, !b[c].sleep && delete b[c]);
        });
        return b[a];
    };
    a.findDotsAtSegment = function(a, b, c, d, e, f, g, h, i) {
        var j = 1 - i, k = A(j, 3), l = A(j, 2), m = i * i, n = m * i, o = k * a + l * 3 * i * c + j * 3 * i * i * e + n * g, p = k * b + l * 3 * i * d + j * 3 * i * i * f + n * h, q = a + 2 * i * (c - a) + m * (e - 2 * c + a), r = b + 2 * i * (d - b) + m * (f - 2 * d + b), s = c + 2 * i * (e - c) + m * (g - 2 * e + c), t = d + 2 * i * (f - d) + m * (h - 2 * f + d), u = j * a + i * c, v = j * b + i * d, x = j * e + i * g, y = j * f + i * h, z = 90 - w.atan2(q - s, r - t) * 180 / B;
        (q > s || r < t) && (z += 180);
        return {
            x: o,
            y: p,
            m: {
                x: q,
                y: r
            },
            n: {
                x: s,
                y: t
            },
            start: {
                x: u,
                y: v
            },
            end: {
                x: x,
                y: y
            },
            alpha: z
        };
    }, a.bezierBBox = function(b, c, d, e, f, g, h, i) {
        a.is(b, "array") || (b = [ b, c, d, e, f, g, h, i ]);
        var j = bQ.apply(null, b);
        return {
            x: j.min.x,
            y: j.min.y,
            x2: j.max.x,
            y2: j.max.y,
            width: j.max.x - j.min.x,
            height: j.max.y - j.min.y
        };
    }, a.isPointInsideBBox = function(a, b, c) {
        return b >= a.x && b <= a.x2 && c >= a.y && c <= a.y2;
    }, a.isBBoxIntersect = function(b, c) {
        var d = a.isPointInsideBBox;
        return d(c, b.x, b.y) || d(c, b.x2, b.y) || d(c, b.x, b.y2) || d(c, b.x2, b.y2) || d(b, c.x, c.y) || d(b, c.x2, c.y) || d(b, c.x, c.y2) || d(b, c.x2, c.y2) || (b.x < c.x2 && b.x > c.x || c.x < b.x2 && c.x > b.x) && (b.y < c.y2 && b.y > c.y || c.y < b.y2 && c.y > b.y);
    }, a.pathIntersection = function(a, b) {
        return bH(a, b);
    }, a.pathIntersectionNumber = function(a, b) {
        return bH(a, b, 1);
    }, a.isPointInsidePath = function(b, c, d) {
        var e = a.pathBBox(b);
        return a.isPointInsideBBox(e, c, d) && bH(b, [ [ "M", c, d ], [ "H", e.x2 + 10 ] ], 1) % 2 == 1;
    }, a._removedFactory = function(a) {
        return function() {
            eve("raphael.log", null, "Raphaël: you are calling to method “" + a + "” of removed object", a);
        };
    };
    var bI = a.pathBBox = function(a) {
        var b = bz(a);
        if (b.bbox) return b.bbox;
        if (!a) return {
            x: 0,
            y: 0,
            width: 0,
            height: 0,
            x2: 0,
            y2: 0
        };
        a = bR(a);
        var c = 0, d = 0, e = [], f = [], g;
        for (var h = 0, i = a.length; h < i; h++) {
            g = a[h];
            if (g[0] == "M") c = g[1], d = g[2], e.push(c), f.push(d); else {
                var j = bQ(c, d, g[1], g[2], g[3], g[4], g[5], g[6]);
                e = e[n](j.min.x, j.max.x), f = f[n](j.min.y, j.max.y), c = g[5], d = g[6];
            }
        }
        var k = y[m](0, e), l = y[m](0, f), o = x[m](0, e), p = x[m](0, f), q = {
            x: k,
            y: l,
            x2: o,
            y2: p,
            width: o - k,
            height: p - l
        };
        b.bbox = bm(q);
        return q;
    }, bJ = function(b) {
        var c = bm(b);
        c.toString = a._path2string;
        return c;
    }, bK = a._pathToRelative = function(b) {
        var c = bz(b);
        if (c.rel) return bJ(c.rel);
        if (!a.is(b, E) || !a.is(b && b[0], E)) b = a.parsePathString(b);
        var d = [], e = 0, f = 0, g = 0, h = 0, i = 0;
        b[0][0] == "M" && (e = b[0][1], f = b[0][2], g = e, h = f, i++, d.push([ "M", e, f ]));
        for (var j = i, k = b.length; j < k; j++) {
            var l = d[j] = [], m = b[j];
            if (m[0] != v.call(m[0])) {
                l[0] = v.call(m[0]);
                switch (l[0]) {
                  case "a":
                    l[1] = m[1], l[2] = m[2], l[3] = m[3], l[4] = m[4], l[5] = m[5], l[6] = +(m[6] - e).toFixed(3), 
                    l[7] = +(m[7] - f).toFixed(3);
                    break;

                  case "v":
                    l[1] = +(m[1] - f).toFixed(3);
                    break;

                  case "m":
                    g = m[1], h = m[2];

                  default:
                    for (var n = 1, o = m.length; n < o; n++) l[n] = +(m[n] - (n % 2 ? e : f)).toFixed(3);
                }
            } else {
                l = d[j] = [], m[0] == "m" && (g = m[1] + e, h = m[2] + f);
                for (var p = 0, q = m.length; p < q; p++) d[j][p] = m[p];
            }
            var r = d[j].length;
            switch (d[j][0]) {
              case "z":
                e = g, f = h;
                break;

              case "h":
                e += +d[j][r - 1];
                break;

              case "v":
                f += +d[j][r - 1];
                break;

              default:
                e += +d[j][r - 2], f += +d[j][r - 1];
            }
        }
        d.toString = a._path2string, c.rel = bJ(d);
        return d;
    }, bL = a._pathToAbsolute = function(b) {
        var c = bz(b);
        if (c.abs) return bJ(c.abs);
        if (!a.is(b, E) || !a.is(b && b[0], E)) b = a.parsePathString(b);
        if (!b || !b.length) return [ [ "M", 0, 0 ] ];
        var d = [], e = 0, f = 0, g = 0, h = 0, i = 0;
        b[0][0] == "M" && (e = +b[0][1], f = +b[0][2], g = e, h = f, i++, d[0] = [ "M", e, f ]);
        var j = b.length == 3 && b[0][0] == "M" && b[1][0].toUpperCase() == "R" && b[2][0].toUpperCase() == "Z";
        for (var k, l, m = i, o = b.length; m < o; m++) {
            d.push(k = []), l = b[m];
            if (l[0] != S.call(l[0])) {
                k[0] = S.call(l[0]);
                switch (k[0]) {
                  case "A":
                    k[1] = l[1], k[2] = l[2], k[3] = l[3], k[4] = l[4], k[5] = l[5], k[6] = +(l[6] + e), 
                    k[7] = +(l[7] + f);
                    break;

                  case "V":
                    k[1] = +l[1] + f;
                    break;

                  case "H":
                    k[1] = +l[1] + e;
                    break;

                  case "R":
                    var p = [ e, f ][n](l.slice(1));
                    for (var q = 2, r = p.length; q < r; q++) p[q] = +p[q] + e, p[++q] = +p[q] + f;
                    d.pop(), d = d[n](by(p, j));
                    break;

                  case "M":
                    g = +l[1] + e, h = +l[2] + f;

                  default:
                    for (q = 1, r = l.length; q < r; q++) k[q] = +l[q] + (q % 2 ? e : f);
                }
            } else if (l[0] == "R") p = [ e, f ][n](l.slice(1)), d.pop(), d = d[n](by(p, j)), 
            k = [ "R" ][n](l.slice(-2)); else for (var s = 0, t = l.length; s < t; s++) k[s] = l[s];
            switch (k[0]) {
              case "Z":
                e = g, f = h;
                break;

              case "H":
                e = k[1];
                break;

              case "V":
                f = k[1];
                break;

              case "M":
                g = k[k.length - 2], h = k[k.length - 1];

              default:
                e = k[k.length - 2], f = k[k.length - 1];
            }
        }
        d.toString = a._path2string, c.abs = bJ(d);
        return d;
    }, bM = function(a, b, c, d) {
        return [ a, b, c, d, c, d ];
    }, bN = function(a, b, c, d, e, f) {
        var g = 1 / 3, h = 2 / 3;
        return [ g * a + h * c, g * b + h * d, g * e + h * c, g * f + h * d, e, f ];
    }, bO = function(a, b, c, d, e, f, g, h, i, j) {
        var k = B * 120 / 180, l = B / 180 * (+e || 0), m = [], o, p = bv(function(a, b, c) {
            var d = a * w.cos(c) - b * w.sin(c), e = a * w.sin(c) + b * w.cos(c);
            return {
                x: d,
                y: e
            };
        });
        if (!j) {
            o = p(a, b, -l), a = o.x, b = o.y, o = p(h, i, -l), h = o.x, i = o.y;
            var q = w.cos(B / 180 * e), r = w.sin(B / 180 * e), t = (a - h) / 2, u = (b - i) / 2, v = t * t / (c * c) + u * u / (d * d);
            v > 1 && (v = w.sqrt(v), c = v * c, d = v * d);
            var x = c * c, y = d * d, A = (f == g ? -1 : 1) * w.sqrt(z((x * y - x * u * u - y * t * t) / (x * u * u + y * t * t))), C = A * c * u / d + (a + h) / 2, D = A * -d * t / c + (b + i) / 2, E = w.asin(((b - D) / d).toFixed(9)), F = w.asin(((i - D) / d).toFixed(9));
            E = a < C ? B - E : E, F = h < C ? B - F : F, E < 0 && (E = B * 2 + E), F < 0 && (F = B * 2 + F), 
            g && E > F && (E = E - B * 2), !g && F > E && (F = F - B * 2);
        } else E = j[0], F = j[1], C = j[2], D = j[3];
        var G = F - E;
        if (z(G) > k) {
            var H = F, I = h, J = i;
            F = E + k * (g && F > E ? 1 : -1), h = C + c * w.cos(F), i = D + d * w.sin(F), m = bO(h, i, c, d, e, 0, g, I, J, [ F, H, C, D ]);
        }
        G = F - E;
        var K = w.cos(E), L = w.sin(E), M = w.cos(F), N = w.sin(F), O = w.tan(G / 4), P = 4 / 3 * c * O, Q = 4 / 3 * d * O, R = [ a, b ], S = [ a + P * L, b - Q * K ], T = [ h + P * N, i - Q * M ], U = [ h, i ];
        S[0] = 2 * R[0] - S[0], S[1] = 2 * R[1] - S[1];
        if (j) return [ S, T, U ][n](m);
        m = [ S, T, U ][n](m).join()[s](",");
        var V = [];
        for (var W = 0, X = m.length; W < X; W++) V[W] = W % 2 ? p(m[W - 1], m[W], l).y : p(m[W], m[W + 1], l).x;
        return V;
    }, bP = function(a, b, c, d, e, f, g, h, i) {
        var j = 1 - i;
        return {
            x: A(j, 3) * a + A(j, 2) * 3 * i * c + j * 3 * i * i * e + A(i, 3) * g,
            y: A(j, 3) * b + A(j, 2) * 3 * i * d + j * 3 * i * i * f + A(i, 3) * h
        };
    }, bQ = bv(function(a, b, c, d, e, f, g, h) {
        var i = e - 2 * c + a - (g - 2 * e + c), j = 2 * (c - a) - 2 * (e - c), k = a - c, l = (-j + w.sqrt(j * j - 4 * i * k)) / 2 / i, n = (-j - w.sqrt(j * j - 4 * i * k)) / 2 / i, o = [ b, h ], p = [ a, g ], q;
        z(l) > "1e12" && (l = .5), z(n) > "1e12" && (n = .5), l > 0 && l < 1 && (q = bP(a, b, c, d, e, f, g, h, l), 
        p.push(q.x), o.push(q.y)), n > 0 && n < 1 && (q = bP(a, b, c, d, e, f, g, h, n), 
        p.push(q.x), o.push(q.y)), i = f - 2 * d + b - (h - 2 * f + d), j = 2 * (d - b) - 2 * (f - d), 
        k = b - d, l = (-j + w.sqrt(j * j - 4 * i * k)) / 2 / i, n = (-j - w.sqrt(j * j - 4 * i * k)) / 2 / i, 
        z(l) > "1e12" && (l = .5), z(n) > "1e12" && (n = .5), l > 0 && l < 1 && (q = bP(a, b, c, d, e, f, g, h, l), 
        p.push(q.x), o.push(q.y)), n > 0 && n < 1 && (q = bP(a, b, c, d, e, f, g, h, n), 
        p.push(q.x), o.push(q.y));
        return {
            min: {
                x: y[m](0, p),
                y: y[m](0, o)
            },
            max: {
                x: x[m](0, p),
                y: x[m](0, o)
            }
        };
    }), bR = a._path2curve = bv(function(a, b) {
        var c = !b && bz(a);
        if (!b && c.curve) return bJ(c.curve);
        var d = bL(a), e = b && bL(b), f = {
            x: 0,
            y: 0,
            bx: 0,
            by: 0,
            X: 0,
            Y: 0,
            qx: null,
            qy: null
        }, g = {
            x: 0,
            y: 0,
            bx: 0,
            by: 0,
            X: 0,
            Y: 0,
            qx: null,
            qy: null
        }, h = function(a, b) {
            var c, d;
            if (!a) return [ "C", b.x, b.y, b.x, b.y, b.x, b.y ];
            !(a[0] in {
                T: 1,
                Q: 1
            }) && (b.qx = b.qy = null);
            switch (a[0]) {
              case "M":
                b.X = a[1], b.Y = a[2];
                break;

              case "A":
                a = [ "C" ][n](bO[m](0, [ b.x, b.y ][n](a.slice(1))));
                break;

              case "S":
                c = b.x + (b.x - (b.bx || b.x)), d = b.y + (b.y - (b.by || b.y)), a = [ "C", c, d ][n](a.slice(1));
                break;

              case "T":
                b.qx = b.x + (b.x - (b.qx || b.x)), b.qy = b.y + (b.y - (b.qy || b.y)), a = [ "C" ][n](bN(b.x, b.y, b.qx, b.qy, a[1], a[2]));
                break;

              case "Q":
                b.qx = a[1], b.qy = a[2], a = [ "C" ][n](bN(b.x, b.y, a[1], a[2], a[3], a[4]));
                break;

              case "L":
                a = [ "C" ][n](bM(b.x, b.y, a[1], a[2]));
                break;

              case "H":
                a = [ "C" ][n](bM(b.x, b.y, a[1], b.y));
                break;

              case "V":
                a = [ "C" ][n](bM(b.x, b.y, b.x, a[1]));
                break;

              case "Z":
                a = [ "C" ][n](bM(b.x, b.y, b.X, b.Y));
            }
            return a;
        }, i = function(a, b) {
            if (a[b].length > 7) {
                a[b].shift();
                var c = a[b];
                while (c.length) a.splice(b++, 0, [ "C" ][n](c.splice(0, 6)));
                a.splice(b, 1), l = x(d.length, e && e.length || 0);
            }
        }, j = function(a, b, c, f, g) {
            a && b && a[g][0] == "M" && b[g][0] != "M" && (b.splice(g, 0, [ "M", f.x, f.y ]), 
            c.bx = 0, c.by = 0, c.x = a[g][1], c.y = a[g][2], l = x(d.length, e && e.length || 0));
        };
        for (var k = 0, l = x(d.length, e && e.length || 0); k < l; k++) {
            d[k] = h(d[k], f), i(d, k), e && (e[k] = h(e[k], g)), e && i(e, k), j(d, e, f, g, k), 
            j(e, d, g, f, k);
            var o = d[k], p = e && e[k], q = o.length, r = e && p.length;
            f.x = o[q - 2], f.y = o[q - 1], f.bx = Q(o[q - 4]) || f.x, f.by = Q(o[q - 3]) || f.y, 
            g.bx = e && (Q(p[r - 4]) || g.x), g.by = e && (Q(p[r - 3]) || g.y), g.x = e && p[r - 2], 
            g.y = e && p[r - 1];
        }
        e || (c.curve = bJ(d));
        return e ? [ d, e ] : d;
    }, null, bJ), bS = a._parseDots = bv(function(b) {
        var c = [];
        for (var d = 0, e = b.length; d < e; d++) {
            var f = {}, g = b[d].match(/^([^:]*):?([\d\.]*)/);
            f.color = a.getRGB(g[1]);
            if (f.color.error) return null;
            f.color = f.color.hex, g[2] && (f.offset = g[2] + "%"), c.push(f);
        }
        for (d = 1, e = c.length - 1; d < e; d++) if (!c[d].offset) {
            var h = Q(c[d - 1].offset || 0), i = 0;
            for (var j = d + 1; j < e; j++) if (c[j].offset) {
                i = c[j].offset;
                break;
            }
            i || (i = 100, j = e), i = Q(i);
            var k = (i - h) / (j - d + 1);
            for (;d < j; d++) h += k, c[d].offset = h + "%";
        }
        return c;
    }), bT = a._tear = function(a, b) {
        a == b.top && (b.top = a.prev), a == b.bottom && (b.bottom = a.next), a.next && (a.next.prev = a.prev), 
        a.prev && (a.prev.next = a.next);
    }, bU = a._tofront = function(a, b) {
        b.top !== a && (bT(a, b), a.next = null, a.prev = b.top, b.top.next = a, b.top = a);
    }, bV = a._toback = function(a, b) {
        b.bottom !== a && (bT(a, b), a.next = b.bottom, a.prev = null, b.bottom.prev = a, 
        b.bottom = a);
    }, bW = a._insertafter = function(a, b, c) {
        bT(a, c), b == c.top && (c.top = a), b.next && (b.next.prev = a), a.next = b.next, 
        a.prev = b, b.next = a;
    }, bX = a._insertbefore = function(a, b, c) {
        bT(a, c), b == c.bottom && (c.bottom = a), b.prev && (b.prev.next = a), a.prev = b.prev, 
        b.prev = a, a.next = b;
    }, bY = a.toMatrix = function(a, b) {
        var c = bI(a), d = {
            _: {
                transform: p
            },
            getBBox: function() {
                return c;
            }
        };
        b$(d, b);
        return d.matrix;
    }, bZ = a.transformPath = function(a, b) {
        return bj(a, bY(a, b));
    }, b$ = a._extractTransform = function(b, c) {
        if (c == null) return b._.transform;
        c = r(c).replace(/\.{3}|\u2026/g, b._.transform || p);
        var d = a.parseTransformString(c), e = 0, f = 0, g = 0, h = 1, i = 1, j = b._, k = new cb();
        j.transform = d || [];
        if (d) for (var l = 0, m = d.length; l < m; l++) {
            var n = d[l], o = n.length, q = r(n[0]).toLowerCase(), s = n[0] != q, t = s ? k.invert() : 0, u, v, w, x, y;
            q == "t" && o == 3 ? s ? (u = t.x(0, 0), v = t.y(0, 0), w = t.x(n[1], n[2]), x = t.y(n[1], n[2]), 
            k.translate(w - u, x - v)) : k.translate(n[1], n[2]) : q == "r" ? o == 2 ? (y = y || b.getBBox(1), 
            k.rotate(n[1], y.x + y.width / 2, y.y + y.height / 2), e += n[1]) : o == 4 && (s ? (w = t.x(n[2], n[3]), 
            x = t.y(n[2], n[3]), k.rotate(n[1], w, x)) : k.rotate(n[1], n[2], n[3]), e += n[1]) : q == "s" ? o == 2 || o == 3 ? (y = y || b.getBBox(1), 
            k.scale(n[1], n[o - 1], y.x + y.width / 2, y.y + y.height / 2), h *= n[1], i *= n[o - 1]) : o == 5 && (s ? (w = t.x(n[3], n[4]), 
            x = t.y(n[3], n[4]), k.scale(n[1], n[2], w, x)) : k.scale(n[1], n[2], n[3], n[4]), 
            h *= n[1], i *= n[2]) : q == "m" && o == 7 && k.add(n[1], n[2], n[3], n[4], n[5], n[6]), 
            j.dirtyT = 1, b.matrix = k;
        }
        b.matrix = k, j.sx = h, j.sy = i, j.deg = e, j.dx = f = k.e, j.dy = g = k.f, h == 1 && i == 1 && !e && j.bbox ? (j.bbox.x += +f, 
        j.bbox.y += +g) : j.dirtyT = 1;
    }, b_ = function(a) {
        var b = a[0];
        switch (b.toLowerCase()) {
          case "t":
            return [ b, 0, 0 ];

          case "m":
            return [ b, 1, 0, 0, 1, 0, 0 ];

          case "r":
            return a.length == 4 ? [ b, 0, a[2], a[3] ] : [ b, 0 ];

          case "s":
            return a.length == 5 ? [ b, 1, 1, a[3], a[4] ] : a.length == 3 ? [ b, 1, 1 ] : [ b, 1 ];
        }
    }, ca = a._equaliseTransform = function(b, c) {
        c = r(c).replace(/\.{3}|\u2026/g, b), b = a.parseTransformString(b) || [], c = a.parseTransformString(c) || [];
        var d = x(b.length, c.length), e = [], f = [], g = 0, h, i, j, k;
        for (;g < d; g++) {
            j = b[g] || b_(c[g]), k = c[g] || b_(j);
            if (j[0] != k[0] || j[0].toLowerCase() == "r" && (j[2] != k[2] || j[3] != k[3]) || j[0].toLowerCase() == "s" && (j[3] != k[3] || j[4] != k[4])) return;
            e[g] = [], f[g] = [];
            for (h = 0, i = x(j.length, k.length); h < i; h++) h in j && (e[g][h] = j[h]), h in k && (f[g][h] = k[h]);
        }
        return {
            from: e,
            to: f
        };
    };
    a._getContainer = function(b, c, d, e) {
        var f;
        f = e == null && !a.is(b, "object") ? h.doc.getElementById(b) : b;
        if (f != null) {
            if (f.tagName) return c == null ? {
                container: f,
                width: f.style.pixelWidth || f.offsetWidth,
                height: f.style.pixelHeight || f.offsetHeight
            } : {
                container: f,
                width: c,
                height: d
            };
            return {
                container: 1,
                x: b,
                y: c,
                width: d,
                height: e
            };
        }
    }, a.pathToRelative = bK, a._engine = {}, a.path2curve = bR, a.matrix = function(a, b, c, d, e, f) {
        return new cb(a, b, c, d, e, f);
    }, function(b) {
        function d(a) {
            var b = w.sqrt(c(a));
            a[0] && (a[0] /= b), a[1] && (a[1] /= b);
        }
        function c(a) {
            return a[0] * a[0] + a[1] * a[1];
        }
        b.add = function(a, b, c, d, e, f) {
            var g = [ [], [], [] ], h = [ [ this.a, this.c, this.e ], [ this.b, this.d, this.f ], [ 0, 0, 1 ] ], i = [ [ a, c, e ], [ b, d, f ], [ 0, 0, 1 ] ], j, k, l, m;
            a && a instanceof cb && (i = [ [ a.a, a.c, a.e ], [ a.b, a.d, a.f ], [ 0, 0, 1 ] ]);
            for (j = 0; j < 3; j++) for (k = 0; k < 3; k++) {
                m = 0;
                for (l = 0; l < 3; l++) m += h[j][l] * i[l][k];
                g[j][k] = m;
            }
            this.a = g[0][0], this.b = g[1][0], this.c = g[0][1], this.d = g[1][1], this.e = g[0][2], 
            this.f = g[1][2];
        }, b.invert = function() {
            var a = this, b = a.a * a.d - a.b * a.c;
            return new cb(a.d / b, -a.b / b, -a.c / b, a.a / b, (a.c * a.f - a.d * a.e) / b, (a.b * a.e - a.a * a.f) / b);
        }, b.clone = function() {
            return new cb(this.a, this.b, this.c, this.d, this.e, this.f);
        }, b.translate = function(a, b) {
            this.add(1, 0, 0, 1, a, b);
        }, b.scale = function(a, b, c, d) {
            b == null && (b = a), (c || d) && this.add(1, 0, 0, 1, c, d), this.add(a, 0, 0, b, 0, 0), 
            (c || d) && this.add(1, 0, 0, 1, -c, -d);
        }, b.rotate = function(b, c, d) {
            b = a.rad(b), c = c || 0, d = d || 0;
            var e = +w.cos(b).toFixed(9), f = +w.sin(b).toFixed(9);
            this.add(e, f, -f, e, c, d), this.add(1, 0, 0, 1, -c, -d);
        }, b.x = function(a, b) {
            return a * this.a + b * this.c + this.e;
        }, b.y = function(a, b) {
            return a * this.b + b * this.d + this.f;
        }, b.get = function(a) {
            return +this[r.fromCharCode(97 + a)].toFixed(4);
        }, b.toString = function() {
            return a.svg ? "matrix(" + [ this.get(0), this.get(1), this.get(2), this.get(3), this.get(4), this.get(5) ].join() + ")" : [ this.get(0), this.get(2), this.get(1), this.get(3), 0, 0 ].join();
        }, b.toFilter = function() {
            return "progid:DXImageTransform.Microsoft.Matrix(M11=" + this.get(0) + ", M12=" + this.get(2) + ", M21=" + this.get(1) + ", M22=" + this.get(3) + ", Dx=" + this.get(4) + ", Dy=" + this.get(5) + ", sizingmethod='auto expand')";
        }, b.offset = function() {
            return [ this.e.toFixed(4), this.f.toFixed(4) ];
        }, b.split = function() {
            var b = {};
            b.dx = this.e, b.dy = this.f;
            var e = [ [ this.a, this.c ], [ this.b, this.d ] ];
            b.scalex = w.sqrt(c(e[0])), d(e[0]), b.shear = e[0][0] * e[1][0] + e[0][1] * e[1][1], 
            e[1] = [ e[1][0] - e[0][0] * b.shear, e[1][1] - e[0][1] * b.shear ], b.scaley = w.sqrt(c(e[1])), 
            d(e[1]), b.shear /= b.scaley;
            var f = -e[0][1], g = e[1][1];
            g < 0 ? (b.rotate = a.deg(w.acos(g)), f < 0 && (b.rotate = 360 - b.rotate)) : b.rotate = a.deg(w.asin(f)), 
            b.isSimple = !+b.shear.toFixed(9) && (b.scalex.toFixed(9) == b.scaley.toFixed(9) || !b.rotate), 
            b.isSuperSimple = !+b.shear.toFixed(9) && b.scalex.toFixed(9) == b.scaley.toFixed(9) && !b.rotate, 
            b.noRotation = !+b.shear.toFixed(9) && !b.rotate;
            return b;
        }, b.toTransformString = function(a) {
            var b = a || this[s]();
            if (b.isSimple) {
                b.scalex = +b.scalex.toFixed(4), b.scaley = +b.scaley.toFixed(4), b.rotate = +b.rotate.toFixed(4);
                return (b.dx || b.dy ? "t" + [ b.dx, b.dy ] : p) + (b.scalex != 1 || b.scaley != 1 ? "s" + [ b.scalex, b.scaley, 0, 0 ] : p) + (b.rotate ? "r" + [ b.rotate, 0, 0 ] : p);
            }
            return "m" + [ this.get(0), this.get(1), this.get(2), this.get(3), this.get(4), this.get(5) ];
        };
    }(cb.prototype);
    var cc = navigator.userAgent.match(/Version\/(.*?)\s/) || navigator.userAgent.match(/Chrome\/(\d+)/);
    navigator.vendor == "Apple Computer, Inc." && (cc && cc[1] < 4 || navigator.platform.slice(0, 2) == "iP") || navigator.vendor == "Google Inc." && cc && cc[1] < 8 ? k.safari = function() {
        var a = this.rect(-99, -99, this.width + 99, this.height + 99).attr({
            stroke: "none"
        });
        setTimeout(function() {
            a.remove();
        });
    } : k.safari = be;
    var cd = function() {
        this.returnValue = !1;
    }, ce = function() {
        return this.originalEvent.preventDefault();
    }, cf = function() {
        this.cancelBubble = !0;
    }, cg = function() {
        return this.originalEvent.stopPropagation();
    }, ch = function() {
        if (h.doc.addEventListener) return function(a, b, c, d) {
            var e = o && u[b] ? u[b] : b, f = function(e) {
                var f = h.doc.documentElement.scrollTop || h.doc.body.scrollTop, i = h.doc.documentElement.scrollLeft || h.doc.body.scrollLeft, j = e.clientX + i, k = e.clientY + f;
                if (o && u[g](b)) for (var l = 0, m = e.targetTouches && e.targetTouches.length; l < m; l++) if (e.targetTouches[l].target == a) {
                    var n = e;
                    e = e.targetTouches[l], e.originalEvent = n, e.preventDefault = ce, e.stopPropagation = cg;
                    break;
                }
                return c.call(d, e, j, k);
            };
            a.addEventListener(e, f, !1);
            return function() {
                a.removeEventListener(e, f, !1);
                return !0;
            };
        };
        if (h.doc.attachEvent) return function(a, b, c, d) {
            var e = function(a) {
                a = a || h.win.event;
                var b = h.doc.documentElement.scrollTop || h.doc.body.scrollTop, e = h.doc.documentElement.scrollLeft || h.doc.body.scrollLeft, f = a.clientX + e, g = a.clientY + b;
                a.preventDefault = a.preventDefault || cd, a.stopPropagation = a.stopPropagation || cf;
                return c.call(d, a, f, g);
            };
            a.attachEvent("on" + b, e);
            var f = function() {
                a.detachEvent("on" + b, e);
                return !0;
            };
            return f;
        };
    }(), ci = [], cj = function(a) {
        var b = a.clientX, c = a.clientY, d = h.doc.documentElement.scrollTop || h.doc.body.scrollTop, e = h.doc.documentElement.scrollLeft || h.doc.body.scrollLeft, f, g = ci.length;
        while (g--) {
            f = ci[g];
            if (o) {
                var i = a.touches.length, j;
                while (i--) {
                    j = a.touches[i];
                    if (j.identifier == f.el._drag.id) {
                        b = j.clientX, c = j.clientY, (a.originalEvent ? a.originalEvent : a).preventDefault();
                        break;
                    }
                }
            } else a.preventDefault();
            var k = f.el.node, l, m = k.nextSibling, n = k.parentNode, p = k.style.display;
            h.win.opera && n.removeChild(k), k.style.display = "none", l = f.el.paper.getElementByPoint(b, c), 
            k.style.display = p, h.win.opera && (m ? n.insertBefore(k, m) : n.appendChild(k)), 
            l && eve("raphael.drag.over." + f.el.id, f.el, l), b += e, c += d, eve("raphael.drag.move." + f.el.id, f.move_scope || f.el, b - f.el._drag.x, c - f.el._drag.y, b, c, a);
        }
    }, ck = function(b) {
        a.unmousemove(cj).unmouseup(ck);
        var c = ci.length, d;
        while (c--) d = ci[c], d.el._drag = {}, eve("raphael.drag.end." + d.el.id, d.end_scope || d.start_scope || d.move_scope || d.el, b);
        ci = [];
    }, cl = a.el = {};
    for (var cm = t.length; cm--; ) (function(b) {
        a[b] = cl[b] = function(c, d) {
            a.is(c, "function") && (this.events = this.events || [], this.events.push({
                name: b,
                f: c,
                unbind: ch(this.shape || this.node || h.doc, b, c, d || this)
            }));
            return this;
        }, a["un" + b] = cl["un" + b] = function(a) {
            var c = this.events || [], d = c.length;
            while (d--) if (c[d].name == b && c[d].f == a) {
                c[d].unbind(), c.splice(d, 1), !c.length && delete this.events;
                return this;
            }
            return this;
        };
    })(t[cm]);
    cl.data = function(b, c) {
        var d = bb[this.id] = bb[this.id] || {};
        if (arguments.length == 1) {
            if (a.is(b, "object")) {
                for (var e in b) b[g](e) && this.data(e, b[e]);
                return this;
            }
            eve("raphael.data.get." + this.id, this, d[b], b);
            return d[b];
        }
        d[b] = c, eve("raphael.data.set." + this.id, this, c, b);
        return this;
    }, cl.removeData = function(a) {
        a == null ? bb[this.id] = {} : bb[this.id] && delete bb[this.id][a];
        return this;
    }, cl.hover = function(a, b, c, d) {
        return this.mouseover(a, c).mouseout(b, d || c);
    }, cl.unhover = function(a, b) {
        return this.unmouseover(a).unmouseout(b);
    };
    var cn = [];
    cl.drag = function(b, c, d, e, f, g) {
        function i(i) {
            (i.originalEvent || i).preventDefault();
            var j = h.doc.documentElement.scrollTop || h.doc.body.scrollTop, k = h.doc.documentElement.scrollLeft || h.doc.body.scrollLeft;
            this._drag.x = i.clientX + k, this._drag.y = i.clientY + j, this._drag.id = i.identifier, 
            !ci.length && a.mousemove(cj).mouseup(ck), ci.push({
                el: this,
                move_scope: e,
                start_scope: f,
                end_scope: g
            }), c && eve.on("raphael.drag.start." + this.id, c), b && eve.on("raphael.drag.move." + this.id, b), 
            d && eve.on("raphael.drag.end." + this.id, d), eve("raphael.drag.start." + this.id, f || e || this, i.clientX + k, i.clientY + j, i);
        }
        this._drag = {}, cn.push({
            el: this,
            start: i
        }), this.mousedown(i);
        return this;
    }, cl.onDragOver = function(a) {
        a ? eve.on("raphael.drag.over." + this.id, a) : eve.unbind("raphael.drag.over." + this.id);
    }, cl.undrag = function() {
        var b = cn.length;
        while (b--) cn[b].el == this && (this.unmousedown(cn[b].start), cn.splice(b, 1), 
        eve.unbind("raphael.drag.*." + this.id));
        !cn.length && a.unmousemove(cj).unmouseup(ck);
    }, k.circle = function(b, c, d) {
        var e = a._engine.circle(this, b || 0, c || 0, d || 0);
        this.__set__ && this.__set__.push(e);
        return e;
    }, k.rect = function(b, c, d, e, f) {
        var g = a._engine.rect(this, b || 0, c || 0, d || 0, e || 0, f || 0);
        this.__set__ && this.__set__.push(g);
        return g;
    }, k.ellipse = function(b, c, d, e) {
        var f = a._engine.ellipse(this, b || 0, c || 0, d || 0, e || 0);
        this.__set__ && this.__set__.push(f);
        return f;
    }, k.path = function(b) {
        b && !a.is(b, D) && !a.is(b[0], E) && (b += p);
        var c = a._engine.path(a.format[m](a, arguments), this);
        this.__set__ && this.__set__.push(c);
        return c;
    }, k.image = function(b, c, d, e, f) {
        var g = a._engine.image(this, b || "about:blank", c || 0, d || 0, e || 0, f || 0);
        this.__set__ && this.__set__.push(g);
        return g;
    }, k.text = function(b, c, d) {
        var e = a._engine.text(this, b || 0, c || 0, r(d));
        this.__set__ && this.__set__.push(e);
        return e;
    }, k.set = function(b) {
        !a.is(b, "array") && (b = Array.prototype.splice.call(arguments, 0, arguments.length));
        var c = new cG(b);
        this.__set__ && this.__set__.push(c);
        return c;
    }, k.setStart = function(a) {
        this.__set__ = a || this.set();
    }, k.setFinish = function(a) {
        var b = this.__set__;
        delete this.__set__;
        return b;
    }, k.setSize = function(b, c) {
        return a._engine.setSize.call(this, b, c);
    }, k.setViewBox = function(b, c, d, e, f) {
        return a._engine.setViewBox.call(this, b, c, d, e, f);
    }, k.top = k.bottom = null, k.raphael = a;
    var co = function(a) {
        var b = a.getBoundingClientRect(), c = a.ownerDocument, d = c.body, e = c.documentElement, f = e.clientTop || d.clientTop || 0, g = e.clientLeft || d.clientLeft || 0, i = b.top + (h.win.pageYOffset || e.scrollTop || d.scrollTop) - f, j = b.left + (h.win.pageXOffset || e.scrollLeft || d.scrollLeft) - g;
        return {
            y: i,
            x: j
        };
    };
    k.getElementByPoint = function(a, b) {
        var c = this, d = c.canvas, e = h.doc.elementFromPoint(a, b);
        if (h.win.opera && e.tagName == "svg") {
            var f = co(d), g = d.createSVGRect();
            g.x = a - f.x, g.y = b - f.y, g.width = g.height = 1;
            var i = d.getIntersectionList(g, null);
            i.length && (e = i[i.length - 1]);
        }
        if (!e) return null;
        while (e.parentNode && e != d.parentNode && !e.raphael) e = e.parentNode;
        e == c.canvas.parentNode && (e = d), e = e && e.raphael ? c.getById(e.raphaelid) : null;
        return e;
    }, k.getById = function(a) {
        var b = this.bottom;
        while (b) {
            if (b.id == a) return b;
            b = b.next;
        }
        return null;
    }, k.forEach = function(a, b) {
        var c = this.bottom;
        while (c) {
            if (a.call(b, c) === !1) return this;
            c = c.next;
        }
        return this;
    }, k.getElementsByPoint = function(a, b) {
        var c = this.set();
        this.forEach(function(d) {
            d.isPointInside(a, b) && c.push(d);
        });
        return c;
    }, cl.isPointInside = function(b, c) {
        var d = this.realPath = this.realPath || bi[this.type](this);
        return a.isPointInsidePath(d, b, c);
    }, cl.getBBox = function(a) {
        if (this.removed) return {};
        var b = this._;
        if (a) {
            if (b.dirty || !b.bboxwt) this.realPath = bi[this.type](this), b.bboxwt = bI(this.realPath), 
            b.bboxwt.toString = cq, b.dirty = 0;
            return b.bboxwt;
        }
        if (b.dirty || b.dirtyT || !b.bbox) {
            if (b.dirty || !this.realPath) b.bboxwt = 0, this.realPath = bi[this.type](this);
            b.bbox = bI(bj(this.realPath, this.matrix)), b.bbox.toString = cq, b.dirty = b.dirtyT = 0;
        }
        return b.bbox;
    }, cl.clone = function() {
        if (this.removed) return null;
        var a = this.paper[this.type]().attr(this.attr());
        this.__set__ && this.__set__.push(a);
        return a;
    }, cl.glow = function(a) {
        if (this.type == "text") return null;
        a = a || {};
        var b = {
            width: (a.width || 10) + (+this.attr("stroke-width") || 1),
            fill: a.fill || !1,
            opacity: a.opacity || .5,
            offsetx: a.offsetx || 0,
            offsety: a.offsety || 0,
            color: a.color || "#000"
        }, c = b.width / 2, d = this.paper, e = d.set(), f = this.realPath || bi[this.type](this);
        f = this.matrix ? bj(f, this.matrix) : f;
        for (var g = 1; g < c + 1; g++) e.push(d.path(f).attr({
            stroke: b.color,
            fill: b.fill ? b.color : "none",
            "stroke-linejoin": "round",
            "stroke-linecap": "round",
            "stroke-width": +(b.width / c * g).toFixed(3),
            opacity: +(b.opacity / c).toFixed(3)
        }));
        return e.insertBefore(this).translate(b.offsetx, b.offsety);
    };
    var cr = {}, cs = function(b, c, d, e, f, g, h, i, j) {
        return j == null ? bB(b, c, d, e, f, g, h, i) : a.findDotsAtSegment(b, c, d, e, f, g, h, i, bC(b, c, d, e, f, g, h, i, j));
    }, ct = function(b, c) {
        return function(d, e, f) {
            d = bR(d);
            var g, h, i, j, k = "", l = {}, m, n = 0;
            for (var o = 0, p = d.length; o < p; o++) {
                i = d[o];
                if (i[0] == "M") g = +i[1], h = +i[2]; else {
                    j = cs(g, h, i[1], i[2], i[3], i[4], i[5], i[6]);
                    if (n + j > e) {
                        if (c && !l.start) {
                            m = cs(g, h, i[1], i[2], i[3], i[4], i[5], i[6], e - n), k += [ "C" + m.start.x, m.start.y, m.m.x, m.m.y, m.x, m.y ];
                            if (f) return k;
                            l.start = k, k = [ "M" + m.x, m.y + "C" + m.n.x, m.n.y, m.end.x, m.end.y, i[5], i[6] ].join(), 
                            n += j, g = +i[5], h = +i[6];
                            continue;
                        }
                        if (!b && !c) {
                            m = cs(g, h, i[1], i[2], i[3], i[4], i[5], i[6], e - n);
                            return {
                                x: m.x,
                                y: m.y,
                                alpha: m.alpha
                            };
                        }
                    }
                    n += j, g = +i[5], h = +i[6];
                }
                k += i.shift() + i;
            }
            l.end = k, m = b ? n : c ? l : a.findDotsAtSegment(g, h, i[0], i[1], i[2], i[3], i[4], i[5], 1), 
            m.alpha && (m = {
                x: m.x,
                y: m.y,
                alpha: m.alpha
            });
            return m;
        };
    }, cu = ct(1), cv = ct(), cw = ct(0, 1);
    a.getTotalLength = cu, a.getPointAtLength = cv, a.getSubpath = function(a, b, c) {
        if (this.getTotalLength(a) - c < 1e-6) return cw(a, b).end;
        var d = cw(a, c, 1);
        return b ? cw(d, b).end : d;
    }, cl.getTotalLength = function() {
        if (this.type == "path") {
            if (this.node.getTotalLength) return this.node.getTotalLength();
            return cu(this.attrs.path);
        }
    }, cl.getPointAtLength = function(a) {
        if (this.type == "path") return cv(this.attrs.path, a);
    }, cl.getSubpath = function(b, c) {
        if (this.type == "path") return a.getSubpath(this.attrs.path, b, c);
    };
    var cx = a.easing_formulas = {
        linear: function(a) {
            return a;
        },
        "<": function(a) {
            return A(a, 1.7);
        },
        ">": function(a) {
            return A(a, .48);
        },
        "<>": function(a) {
            var b = .48 - a / 1.04, c = w.sqrt(.1734 + b * b), d = c - b, e = A(z(d), 1 / 3) * (d < 0 ? -1 : 1), f = -c - b, g = A(z(f), 1 / 3) * (f < 0 ? -1 : 1), h = e + g + .5;
            return (1 - h) * 3 * h * h + h * h * h;
        },
        backIn: function(a) {
            var b = 1.70158;
            return a * a * ((b + 1) * a - b);
        },
        backOut: function(a) {
            a = a - 1;
            var b = 1.70158;
            return a * a * ((b + 1) * a + b) + 1;
        },
        elastic: function(a) {
            if (a == !!a) return a;
            return A(2, -10 * a) * w.sin((a - .075) * 2 * B / .3) + 1;
        },
        bounce: function(a) {
            var b = 7.5625, c = 2.75, d;
            a < 1 / c ? d = b * a * a : a < 2 / c ? (a -= 1.5 / c, d = b * a * a + .75) : a < 2.5 / c ? (a -= 2.25 / c, 
            d = b * a * a + .9375) : (a -= 2.625 / c, d = b * a * a + .984375);
            return d;
        }
    };
    cx.easeIn = cx["ease-in"] = cx["<"], cx.easeOut = cx["ease-out"] = cx[">"], cx.easeInOut = cx["ease-in-out"] = cx["<>"], 
    cx["back-in"] = cx.backIn, cx["back-out"] = cx.backOut;
    var cy = [], cz = window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || window.oRequestAnimationFrame || window.msRequestAnimationFrame || function(a) {
        setTimeout(a, 16);
    }, cA = function() {
        var b = +new Date(), c = 0;
        for (;c < cy.length; c++) {
            var d = cy[c];
            if (d.el.removed || d.paused) continue;
            var e = b - d.start, f = d.ms, h = d.easing, i = d.from, j = d.diff, k = d.to, l = d.t, m = d.el, o = {}, p, r = {}, s;
            d.initstatus ? (e = (d.initstatus * d.anim.top - d.prev) / (d.percent - d.prev) * f, 
            d.status = d.initstatus, delete d.initstatus, d.stop && cy.splice(c--, 1)) : d.status = (d.prev + (d.percent - d.prev) * (e / f)) / d.anim.top;
            if (e < 0) continue;
            if (e < f) {
                var t = h(e / f);
                for (var u in i) if (i[g](u)) {
                    switch (U[u]) {
                      case C:
                        p = +i[u] + t * f * j[u];
                        break;

                      case "colour":
                        p = "rgb(" + [ cB(O(i[u].r + t * f * j[u].r)), cB(O(i[u].g + t * f * j[u].g)), cB(O(i[u].b + t * f * j[u].b)) ].join(",") + ")";
                        break;

                      case "path":
                        p = [];
                        for (var v = 0, w = i[u].length; v < w; v++) {
                            p[v] = [ i[u][v][0] ];
                            for (var x = 1, y = i[u][v].length; x < y; x++) p[v][x] = +i[u][v][x] + t * f * j[u][v][x];
                            p[v] = p[v].join(q);
                        }
                        p = p.join(q);
                        break;

                      case "transform":
                        if (j[u].real) {
                            p = [];
                            for (v = 0, w = i[u].length; v < w; v++) {
                                p[v] = [ i[u][v][0] ];
                                for (x = 1, y = i[u][v].length; x < y; x++) p[v][x] = i[u][v][x] + t * f * j[u][v][x];
                            }
                        } else {
                            var z = function(a) {
                                return +i[u][a] + t * f * j[u][a];
                            };
                            p = [ [ "m", z(0), z(1), z(2), z(3), z(4), z(5) ] ];
                        }
                        break;

                      case "csv":
                        if (u == "clip-rect") {
                            p = [], v = 4;
                            while (v--) p[v] = +i[u][v] + t * f * j[u][v];
                        }
                        break;

                      default:
                        var A = [][n](i[u]);
                        p = [], v = m.paper.customAttributes[u].length;
                        while (v--) p[v] = +A[v] + t * f * j[u][v];
                    }
                    o[u] = p;
                }
                m.attr(o), function(a, b, c) {
                    setTimeout(function() {
                        eve("raphael.anim.frame." + a, b, c);
                    });
                }(m.id, m, d.anim);
            } else {
                (function(b, c, d) {
                    setTimeout(function() {
                        eve("raphael.anim.frame." + c.id, c, d), eve("raphael.anim.finish." + c.id, c, d), 
                        a.is(b, "function") && b.call(c);
                    });
                })(d.callback, m, d.anim), m.attr(k), cy.splice(c--, 1);
                if (d.repeat > 1 && !d.next) {
                    for (s in k) k[g](s) && (r[s] = d.totalOrigin[s]);
                    d.el.attr(r), cE(d.anim, d.el, d.anim.percents[0], null, d.totalOrigin, d.repeat - 1);
                }
                d.next && !d.stop && cE(d.anim, d.el, d.next, null, d.totalOrigin, d.repeat);
            }
        }
        a.svg && m && m.paper && m.paper.safari(), cy.length && cz(cA);
    }, cB = function(a) {
        return a > 255 ? 255 : a < 0 ? 0 : a;
    };
    cl.animateWith = function(b, c, d, e, f, g) {
        var h = this;
        if (h.removed) {
            g && g.call(h);
            return h;
        }
        var i = d instanceof cD ? d : a.animation(d, e, f, g), j, k;
        cE(i, h, i.percents[0], null, h.attr());
        for (var l = 0, m = cy.length; l < m; l++) if (cy[l].anim == c && cy[l].el == b) {
            cy[m - 1].start = cy[l].start;
            break;
        }
        return h;
    }, cl.onAnimation = function(a) {
        a ? eve.on("raphael.anim.frame." + this.id, a) : eve.unbind("raphael.anim.frame." + this.id);
        return this;
    }, cD.prototype.delay = function(a) {
        var b = new cD(this.anim, this.ms);
        b.times = this.times, b.del = +a || 0;
        return b;
    }, cD.prototype.repeat = function(a) {
        var b = new cD(this.anim, this.ms);
        b.del = this.del, b.times = w.floor(x(a, 0)) || 1;
        return b;
    }, a.animation = function(b, c, d, e) {
        if (b instanceof cD) return b;
        if (a.is(d, "function") || !d) e = e || d || null, d = null;
        b = Object(b), c = +c || 0;
        var f = {}, h, i;
        for (i in b) b[g](i) && Q(i) != i && Q(i) + "%" != i && (h = !0, f[i] = b[i]);
        if (!h) return new cD(b, c);
        d && (f.easing = d), e && (f.callback = e);
        return new cD({
            100: f
        }, c);
    }, cl.animate = function(b, c, d, e) {
        var f = this;
        if (f.removed) {
            e && e.call(f);
            return f;
        }
        var g = b instanceof cD ? b : a.animation(b, c, d, e);
        cE(g, f, g.percents[0], null, f.attr());
        return f;
    }, cl.setTime = function(a, b) {
        a && b != null && this.status(a, y(b, a.ms) / a.ms);
        return this;
    }, cl.status = function(a, b) {
        var c = [], d = 0, e, f;
        if (b != null) {
            cE(a, this, -1, y(b, 1));
            return this;
        }
        e = cy.length;
        for (;d < e; d++) {
            f = cy[d];
            if (f.el.id == this.id && (!a || f.anim == a)) {
                if (a) return f.status;
                c.push({
                    anim: f.anim,
                    status: f.status
                });
            }
        }
        if (a) return 0;
        return c;
    }, cl.pause = function(a) {
        for (var b = 0; b < cy.length; b++) cy[b].el.id == this.id && (!a || cy[b].anim == a) && eve("raphael.anim.pause." + this.id, this, cy[b].anim) !== !1 && (cy[b].paused = !0);
        return this;
    }, cl.resume = function(a) {
        for (var b = 0; b < cy.length; b++) if (cy[b].el.id == this.id && (!a || cy[b].anim == a)) {
            var c = cy[b];
            eve("raphael.anim.resume." + this.id, this, c.anim) !== !1 && (delete c.paused, 
            this.status(c.anim, c.status));
        }
        return this;
    }, cl.stop = function(a) {
        for (var b = 0; b < cy.length; b++) cy[b].el.id == this.id && (!a || cy[b].anim == a) && eve("raphael.anim.stop." + this.id, this, cy[b].anim) !== !1 && cy.splice(b--, 1);
        return this;
    }, eve.on("raphael.remove", cF), eve.on("raphael.clear", cF), cl.toString = function() {
        return "Raphaël’s object";
    };
    var cG = function(a) {
        this.items = [], this.length = 0, this.type = "set";
        if (a) for (var b = 0, c = a.length; b < c; b++) a[b] && (a[b].constructor == cl.constructor || a[b].constructor == cG) && (this[this.items.length] = this.items[this.items.length] = a[b], 
        this.length++);
    }, cH = cG.prototype;
    cH.push = function() {
        var a, b;
        for (var c = 0, d = arguments.length; c < d; c++) a = arguments[c], a && (a.constructor == cl.constructor || a.constructor == cG) && (b = this.items.length, 
        this[b] = this.items[b] = a, this.length++);
        return this;
    }, cH.pop = function() {
        this.length && delete this[this.length--];
        return this.items.pop();
    }, cH.forEach = function(a, b) {
        for (var c = 0, d = this.items.length; c < d; c++) if (a.call(b, this.items[c], c) === !1) return this;
        return this;
    };
    for (var cI in cl) cl[g](cI) && (cH[cI] = function(a) {
        return function() {
            var b = arguments;
            return this.forEach(function(c) {
                c[a][m](c, b);
            });
        };
    }(cI));
    cH.attr = function(b, c) {
        if (b && a.is(b, E) && a.is(b[0], "object")) for (var d = 0, e = b.length; d < e; d++) this.items[d].attr(b[d]); else for (var f = 0, g = this.items.length; f < g; f++) this.items[f].attr(b, c);
        return this;
    }, cH.clear = function() {
        while (this.length) this.pop();
    }, cH.splice = function(a, b, c) {
        a = a < 0 ? x(this.length + a, 0) : a, b = x(0, y(this.length - a, b));
        var d = [], e = [], f = [], g;
        for (g = 2; g < arguments.length; g++) f.push(arguments[g]);
        for (g = 0; g < b; g++) e.push(this[a + g]);
        for (;g < this.length - a; g++) d.push(this[a + g]);
        var h = f.length;
        for (g = 0; g < h + d.length; g++) this.items[a + g] = this[a + g] = g < h ? f[g] : d[g - h];
        g = this.items.length = this.length -= b - h;
        while (this[g]) delete this[g++];
        return new cG(e);
    }, cH.exclude = function(a) {
        for (var b = 0, c = this.length; b < c; b++) if (this[b] == a) {
            this.splice(b, 1);
            return !0;
        }
    }, cH.animate = function(b, c, d, e) {
        (a.is(d, "function") || !d) && (e = d || null);
        var f = this.items.length, g = f, h, i = this, j;
        if (!f) return this;
        e && (j = function() {
            !--f && e.call(i);
        }), d = a.is(d, D) ? d : j;
        var k = a.animation(b, c, d, j);
        h = this.items[--g].animate(k);
        while (g--) this.items[g] && !this.items[g].removed && this.items[g].animateWith(h, k, k);
        return this;
    }, cH.insertAfter = function(a) {
        var b = this.items.length;
        while (b--) this.items[b].insertAfter(a);
        return this;
    }, cH.getBBox = function() {
        var a = [], b = [], c = [], d = [];
        for (var e = this.items.length; e--; ) if (!this.items[e].removed) {
            var f = this.items[e].getBBox();
            a.push(f.x), b.push(f.y), c.push(f.x + f.width), d.push(f.y + f.height);
        }
        a = y[m](0, a), b = y[m](0, b), c = x[m](0, c), d = x[m](0, d);
        return {
            x: a,
            y: b,
            x2: c,
            y2: d,
            width: c - a,
            height: d - b
        };
    }, cH.clone = function(a) {
        a = new cG();
        for (var b = 0, c = this.items.length; b < c; b++) a.push(this.items[b].clone());
        return a;
    }, cH.toString = function() {
        return "Raphaël‘s set";
    }, a.registerFont = function(a) {
        if (!a.face) return a;
        this.fonts = this.fonts || {};
        var b = {
            w: a.w,
            face: {},
            glyphs: {}
        }, c = a.face["font-family"];
        for (var d in a.face) a.face[g](d) && (b.face[d] = a.face[d]);
        this.fonts[c] ? this.fonts[c].push(b) : this.fonts[c] = [ b ];
        if (!a.svg) {
            b.face["units-per-em"] = R(a.face["units-per-em"], 10);
            for (var e in a.glyphs) if (a.glyphs[g](e)) {
                var f = a.glyphs[e];
                b.glyphs[e] = {
                    w: f.w,
                    k: {},
                    d: f.d && "M" + f.d.replace(/[mlcxtrv]/g, function(a) {
                        return {
                            l: "L",
                            c: "C",
                            x: "z",
                            t: "m",
                            r: "l",
                            v: "c"
                        }[a] || "M";
                    }) + "z"
                };
                if (f.k) for (var h in f.k) f[g](h) && (b.glyphs[e].k[h] = f.k[h]);
            }
        }
        return a;
    }, k.getFont = function(b, c, d, e) {
        e = e || "normal", d = d || "normal", c = +c || {
            normal: 400,
            bold: 700,
            lighter: 300,
            bolder: 800
        }[c] || 400;
        if (!!a.fonts) {
            var f = a.fonts[b];
            if (!f) {
                var h = new RegExp("(^|\\s)" + b.replace(/[^\w\d\s+!~.:_-]/g, p) + "(\\s|$)", "i");
                for (var i in a.fonts) if (a.fonts[g](i) && h.test(i)) {
                    f = a.fonts[i];
                    break;
                }
            }
            var j;
            if (f) for (var k = 0, l = f.length; k < l; k++) {
                j = f[k];
                if (j.face["font-weight"] == c && (j.face["font-style"] == d || !j.face["font-style"]) && j.face["font-stretch"] == e) break;
            }
            return j;
        }
    }, k.print = function(b, d, e, f, g, h, i) {
        h = h || "middle", i = x(y(i || 0, 1), -1);
        var j = r(e)[s](p), k = 0, l = 0, m = p, n;
        a.is(f, e) && (f = this.getFont(f));
        if (f) {
            n = (g || 16) / f.face["units-per-em"];
            var o = f.face.bbox[s](c), q = +o[0], t = o[3] - o[1], u = 0, v = +o[1] + (h == "baseline" ? t + +f.face.descent : t / 2);
            for (var w = 0, z = j.length; w < z; w++) {
                if (j[w] == "\n") k = 0, B = 0, l = 0, u += t; else {
                    var A = l && f.glyphs[j[w - 1]] || {}, B = f.glyphs[j[w]];
                    k += l ? (A.w || f.w) + (A.k && A.k[j[w]] || 0) + f.w * i : 0, l = 1;
                }
                B && B.d && (m += a.transformPath(B.d, [ "t", k * n, u * n, "s", n, n, q, v, "t", (b - q) / n, (d - v) / n ]));
            }
        }
        return this.path(m).attr({
            fill: "#000",
            stroke: "none"
        });
    }, k.add = function(b) {
        if (a.is(b, "array")) {
            var c = this.set(), e = 0, f = b.length, h;
            for (;e < f; e++) h = b[e] || {}, d[g](h.type) && c.push(this[h.type]().attr(h));
        }
        return c;
    }, a.format = function(b, c) {
        var d = a.is(c, E) ? [ 0 ][n](c) : arguments;
        b && a.is(b, D) && d.length - 1 && (b = b.replace(e, function(a, b) {
            return d[++b] == null ? p : d[b];
        }));
        return b || p;
    }, a.fullfill = function() {
        var a = /\{([^\}]+)\}/g, b = /(?:(?:^|\.)(.+?)(?=\[|\.|$|\()|\[('|")(.+?)\2\])(\(\))?/g, c = function(a, c, d) {
            var e = d;
            c.replace(b, function(a, b, c, d, f) {
                b = b || d, e && (b in e && (e = e[b]), typeof e == "function" && f && (e = e()));
            }), e = (e == null || e == d ? a : e) + "";
            return e;
        };
        return function(b, d) {
            return String(b).replace(a, function(a, b) {
                return c(a, b, d);
            });
        };
    }(), a.ninja = function() {
        i.was ? h.win.Raphael = i.is : delete Raphael;
        return a;
    }, a.st = cH, function(b, c, d) {
        function e() {
            /in/.test(b.readyState) ? setTimeout(e, 9) : a.eve("raphael.DOMload");
        }
        b.readyState == null && b.addEventListener && (b.addEventListener(c, d = function() {
            b.removeEventListener(c, d, !1), b.readyState = "complete";
        }, !1), b.readyState = "loading"), e();
    }(document, "DOMContentLoaded"), i.was ? h.win.Raphael = a : Raphael = a, eve.on("raphael.DOMload", function() {
        b = !0;
    });
}(), window.Raphael.svg && function(a) {
    var b = "hasOwnProperty", c = String, d = parseFloat, e = parseInt, f = Math, g = f.max, h = f.abs, i = f.pow, j = /[, ]+/, k = a.eve, l = "", m = " ", n = "http://www.w3.org/1999/xlink", o = {
        block: "M5,0 0,2.5 5,5z",
        classic: "M5,0 0,2.5 5,5 3.5,3 3.5,2z",
        diamond: "M2.5,0 5,2.5 2.5,5 0,2.5z",
        open: "M6,1 1,3.5 6,6",
        oval: "M2.5,0A2.5,2.5,0,0,1,2.5,5 2.5,2.5,0,0,1,2.5,0z"
    }, p = {};
    a.toString = function() {
        return "Your browser supports SVG.\nYou are running Raphaël " + this.version;
    };
    var q = function(d, e) {
        if (e) {
            typeof d == "string" && (d = q(d));
            for (var f in e) e[b](f) && (f.substring(0, 6) == "xlink:" ? d.setAttributeNS(n, f.substring(6), c(e[f])) : d.setAttribute(f, c(e[f])));
        } else d = a._g.doc.createElementNS("http://www.w3.org/2000/svg", d), d.style && (d.style.webkitTapHighlightColor = "rgba(0,0,0,0)");
        return d;
    }, r = function(b, e) {
        var j = "linear", k = b.id + e, m = .5, n = .5, o = b.node, p = b.paper, r = o.style, s = a._g.doc.getElementById(k);
        if (!s) {
            e = c(e).replace(a._radial_gradient, function(a, b, c) {
                j = "radial";
                if (b && c) {
                    m = d(b), n = d(c);
                    var e = (n > .5) * 2 - 1;
                    i(m - .5, 2) + i(n - .5, 2) > .25 && (n = f.sqrt(.25 - i(m - .5, 2)) * e + .5) && n != .5 && (n = n.toFixed(5) - 1e-5 * e);
                }
                return l;
            }), e = e.split(/\s*\-\s*/);
            if (j == "linear") {
                var t = e.shift();
                t = -d(t);
                if (isNaN(t)) return null;
                var u = [ 0, 0, f.cos(a.rad(t)), f.sin(a.rad(t)) ], v = 1 / (g(h(u[2]), h(u[3])) || 1);
                u[2] *= v, u[3] *= v, u[2] < 0 && (u[0] = -u[2], u[2] = 0), u[3] < 0 && (u[1] = -u[3], 
                u[3] = 0);
            }
            var w = a._parseDots(e);
            if (!w) return null;
            k = k.replace(/[\(\)\s,\xb0#]/g, "_"), b.gradient && k != b.gradient.id && (p.defs.removeChild(b.gradient), 
            delete b.gradient);
            if (!b.gradient) {
                s = q(j + "Gradient", {
                    id: k
                }), b.gradient = s, q(s, j == "radial" ? {
                    fx: m,
                    fy: n
                } : {
                    x1: u[0],
                    y1: u[1],
                    x2: u[2],
                    y2: u[3],
                    gradientTransform: b.matrix.invert()
                }), p.defs.appendChild(s);
                for (var x = 0, y = w.length; x < y; x++) s.appendChild(q("stop", {
                    offset: w[x].offset ? w[x].offset : x ? "100%" : "0%",
                    "stop-color": w[x].color || "#fff"
                }));
            }
        }
        q(o, {
            fill: "url(#" + k + ")",
            opacity: 1,
            "fill-opacity": 1
        }), r.fill = l, r.opacity = 1, r.fillOpacity = 1;
        return 1;
    }, s = function(a) {
        var b = a.getBBox(1);
        q(a.pattern, {
            patternTransform: a.matrix.invert() + " translate(" + b.x + "," + b.y + ")"
        });
    }, t = function(d, e, f) {
        if (d.type == "path") {
            var g = c(e).toLowerCase().split("-"), h = d.paper, i = f ? "end" : "start", j = d.node, k = d.attrs, m = k["stroke-width"], n = g.length, r = "classic", s, t, u, v, w, x = 3, y = 3, z = 5;
            while (n--) switch (g[n]) {
              case "block":
              case "classic":
              case "oval":
              case "diamond":
              case "open":
              case "none":
                r = g[n];
                break;

              case "wide":
                y = 5;
                break;

              case "narrow":
                y = 2;
                break;

              case "long":
                x = 5;
                break;

              case "short":
                x = 2;
            }
            r == "open" ? (x += 2, y += 2, z += 2, u = 1, v = f ? 4 : 1, w = {
                fill: "none",
                stroke: k.stroke
            }) : (v = u = x / 2, w = {
                fill: k.stroke,
                stroke: "none"
            }), d._.arrows ? f ? (d._.arrows.endPath && p[d._.arrows.endPath]--, d._.arrows.endMarker && p[d._.arrows.endMarker]--) : (d._.arrows.startPath && p[d._.arrows.startPath]--, 
            d._.arrows.startMarker && p[d._.arrows.startMarker]--) : d._.arrows = {};
            if (r != "none") {
                var A = "raphael-marker-" + r, B = "raphael-marker-" + i + r + x + y;
                a._g.doc.getElementById(A) ? p[A]++ : (h.defs.appendChild(q(q("path"), {
                    "stroke-linecap": "round",
                    d: o[r],
                    id: A
                })), p[A] = 1);
                var C = a._g.doc.getElementById(B), D;
                C ? (p[B]++, D = C.getElementsByTagName("use")[0]) : (C = q(q("marker"), {
                    id: B,
                    markerHeight: y,
                    markerWidth: x,
                    orient: "auto",
                    refX: v,
                    refY: y / 2
                }), D = q(q("use"), {
                    "xlink:href": "#" + A,
                    transform: (f ? "rotate(180 " + x / 2 + " " + y / 2 + ") " : l) + "scale(" + x / z + "," + y / z + ")",
                    "stroke-width": (1 / ((x / z + y / z) / 2)).toFixed(4)
                }), C.appendChild(D), h.defs.appendChild(C), p[B] = 1), q(D, w);
                var F = u * (r != "diamond" && r != "oval");
                f ? (s = d._.arrows.startdx * m || 0, t = a.getTotalLength(k.path) - F * m) : (s = F * m, 
                t = a.getTotalLength(k.path) - (d._.arrows.enddx * m || 0)), w = {}, w["marker-" + i] = "url(#" + B + ")";
                if (t || s) w.d = Raphael.getSubpath(k.path, s, t);
                q(j, w), d._.arrows[i + "Path"] = A, d._.arrows[i + "Marker"] = B, d._.arrows[i + "dx"] = F, 
                d._.arrows[i + "Type"] = r, d._.arrows[i + "String"] = e;
            } else f ? (s = d._.arrows.startdx * m || 0, t = a.getTotalLength(k.path) - s) : (s = 0, 
            t = a.getTotalLength(k.path) - (d._.arrows.enddx * m || 0)), d._.arrows[i + "Path"] && q(j, {
                d: Raphael.getSubpath(k.path, s, t)
            }), delete d._.arrows[i + "Path"], delete d._.arrows[i + "Marker"], delete d._.arrows[i + "dx"], 
            delete d._.arrows[i + "Type"], delete d._.arrows[i + "String"];
            for (w in p) if (p[b](w) && !p[w]) {
                var G = a._g.doc.getElementById(w);
                G && G.parentNode.removeChild(G);
            }
        }
    }, u = {
        "": [ 0 ],
        none: [ 0 ],
        "-": [ 3, 1 ],
        ".": [ 1, 1 ],
        "-.": [ 3, 1, 1, 1 ],
        "-..": [ 3, 1, 1, 1, 1, 1 ],
        ". ": [ 1, 3 ],
        "- ": [ 4, 3 ],
        "--": [ 8, 3 ],
        "- .": [ 4, 3, 1, 3 ],
        "--.": [ 8, 3, 1, 3 ],
        "--..": [ 8, 3, 1, 3, 1, 3 ]
    }, v = function(a, b, d) {
        b = u[c(b).toLowerCase()];
        if (b) {
            var e = a.attrs["stroke-width"] || "1", f = {
                round: e,
                square: e,
                butt: 0
            }[a.attrs["stroke-linecap"] || d["stroke-linecap"]] || 0, g = [], h = b.length;
            while (h--) g[h] = b[h] * e + (h % 2 ? 1 : -1) * f;
            q(a.node, {
                "stroke-dasharray": g.join(",")
            });
        }
    }, w = function(d, f) {
        var i = d.node, k = d.attrs, m = i.style.visibility;
        i.style.visibility = "hidden";
        for (var o in f) if (f[b](o)) {
            if (!a._availableAttrs[b](o)) continue;
            var p = f[o];
            k[o] = p;
            switch (o) {
              case "blur":
                d.blur(p);
                break;

              case "href":
              case "title":
              case "target":
                var u = i.parentNode;
                if (u.tagName.toLowerCase() != "a") {
                    var w = q("a");
                    u.insertBefore(w, i), w.appendChild(i), u = w;
                }
                o == "target" ? u.setAttributeNS(n, "show", p == "blank" ? "new" : p) : u.setAttributeNS(n, o, p);
                break;

              case "cursor":
                i.style.cursor = p;
                break;

              case "transform":
                d.transform(p);
                break;

              case "arrow-start":
                t(d, p);
                break;

              case "arrow-end":
                t(d, p, 1);
                break;

              case "clip-rect":
                var x = c(p).split(j);
                if (x.length == 4) {
                    d.clip && d.clip.parentNode.parentNode.removeChild(d.clip.parentNode);
                    var z = q("clipPath"), A = q("rect");
                    z.id = a.createUUID(), q(A, {
                        x: x[0],
                        y: x[1],
                        width: x[2],
                        height: x[3]
                    }), z.appendChild(A), d.paper.defs.appendChild(z), q(i, {
                        "clip-path": "url(#" + z.id + ")"
                    }), d.clip = A;
                }
                if (!p) {
                    var B = i.getAttribute("clip-path");
                    if (B) {
                        var C = a._g.doc.getElementById(B.replace(/(^url\(#|\)$)/g, l));
                        C && C.parentNode.removeChild(C), q(i, {
                            "clip-path": l
                        }), delete d.clip;
                    }
                }
                break;

              case "path":
                d.type == "path" && (q(i, {
                    d: p ? k.path = a._pathToAbsolute(p) : "M0,0"
                }), d._.dirty = 1, d._.arrows && ("startString" in d._.arrows && t(d, d._.arrows.startString), 
                "endString" in d._.arrows && t(d, d._.arrows.endString, 1)));
                break;

              case "width":
                i.setAttribute(o, p), d._.dirty = 1;
                if (k.fx) o = "x", p = k.x; else break;

              case "x":
                k.fx && (p = -k.x - (k.width || 0));

              case "rx":
                if (o == "rx" && d.type == "rect") break;

              case "cx":
                i.setAttribute(o, p), d.pattern && s(d), d._.dirty = 1;
                break;

              case "height":
                i.setAttribute(o, p), d._.dirty = 1;
                if (k.fy) o = "y", p = k.y; else break;

              case "y":
                k.fy && (p = -k.y - (k.height || 0));

              case "ry":
                if (o == "ry" && d.type == "rect") break;

              case "cy":
                i.setAttribute(o, p), d.pattern && s(d), d._.dirty = 1;
                break;

              case "r":
                d.type == "rect" ? q(i, {
                    rx: p,
                    ry: p
                }) : i.setAttribute(o, p), d._.dirty = 1;
                break;

              case "src":
                d.type == "image" && i.setAttributeNS(n, "href", p);
                break;

              case "stroke-width":
                if (d._.sx != 1 || d._.sy != 1) p /= g(h(d._.sx), h(d._.sy)) || 1;
                d.paper._vbSize && (p *= d.paper._vbSize), i.setAttribute(o, p), k["stroke-dasharray"] && v(d, k["stroke-dasharray"], f), 
                d._.arrows && ("startString" in d._.arrows && t(d, d._.arrows.startString), "endString" in d._.arrows && t(d, d._.arrows.endString, 1));
                break;

              case "stroke-dasharray":
                v(d, p, f);
                break;

              case "fill":
                var D = c(p).match(a._ISURL);
                if (D) {
                    z = q("pattern");
                    var F = q("image");
                    z.id = a.createUUID(), q(z, {
                        x: 0,
                        y: 0,
                        patternUnits: "userSpaceOnUse",
                        height: 1,
                        width: 1
                    }), q(F, {
                        x: 0,
                        y: 0,
                        "xlink:href": D[1]
                    }), z.appendChild(F), function(b) {
                        a._preload(D[1], function() {
                            var a = this.offsetWidth, c = this.offsetHeight;
                            q(b, {
                                width: a,
                                height: c
                            }), q(F, {
                                width: a,
                                height: c
                            }), d.paper.safari();
                        });
                    }(z), d.paper.defs.appendChild(z), q(i, {
                        fill: "url(#" + z.id + ")"
                    }), d.pattern = z, d.pattern && s(d);
                    break;
                }
                var G = a.getRGB(p);
                if (!G.error) delete f.gradient, delete k.gradient, !a.is(k.opacity, "undefined") && a.is(f.opacity, "undefined") && q(i, {
                    opacity: k.opacity
                }), !a.is(k["fill-opacity"], "undefined") && a.is(f["fill-opacity"], "undefined") && q(i, {
                    "fill-opacity": k["fill-opacity"]
                }); else if ((d.type == "circle" || d.type == "ellipse" || c(p).charAt() != "r") && r(d, p)) {
                    if ("opacity" in k || "fill-opacity" in k) {
                        var H = a._g.doc.getElementById(i.getAttribute("fill").replace(/^url\(#|\)$/g, l));
                        if (H) {
                            var I = H.getElementsByTagName("stop");
                            q(I[I.length - 1], {
                                "stop-opacity": ("opacity" in k ? k.opacity : 1) * ("fill-opacity" in k ? k["fill-opacity"] : 1)
                            });
                        }
                    }
                    k.gradient = p, k.fill = "none";
                    break;
                }
                G[b]("opacity") && q(i, {
                    "fill-opacity": G.opacity > 1 ? G.opacity / 100 : G.opacity
                });

              case "stroke":
                G = a.getRGB(p), i.setAttribute(o, G.hex), o == "stroke" && G[b]("opacity") && q(i, {
                    "stroke-opacity": G.opacity > 1 ? G.opacity / 100 : G.opacity
                }), o == "stroke" && d._.arrows && ("startString" in d._.arrows && t(d, d._.arrows.startString), 
                "endString" in d._.arrows && t(d, d._.arrows.endString, 1));
                break;

              case "gradient":
                (d.type == "circle" || d.type == "ellipse" || c(p).charAt() != "r") && r(d, p);
                break;

              case "opacity":
                k.gradient && !k[b]("stroke-opacity") && q(i, {
                    "stroke-opacity": p > 1 ? p / 100 : p
                });

              case "fill-opacity":
                if (k.gradient) {
                    H = a._g.doc.getElementById(i.getAttribute("fill").replace(/^url\(#|\)$/g, l)), 
                    H && (I = H.getElementsByTagName("stop"), q(I[I.length - 1], {
                        "stop-opacity": p
                    }));
                    break;
                }
                ;

              default:
                o == "font-size" && (p = e(p, 10) + "px");
                var J = o.replace(/(\-.)/g, function(a) {
                    return a.substring(1).toUpperCase();
                });
                i.style[J] = p, d._.dirty = 1, i.setAttribute(o, p);
            }
        }
        y(d, f), i.style.visibility = m;
    }, x = 1.2, y = function(d, f) {
        if (d.type == "text" && !!(f[b]("text") || f[b]("font") || f[b]("font-size") || f[b]("x") || f[b]("y"))) {
            var g = d.attrs, h = d.node, i = h.firstChild ? e(a._g.doc.defaultView.getComputedStyle(h.firstChild, l).getPropertyValue("font-size"), 10) : 10;
            if (f[b]("text")) {
                g.text = f.text;
                while (h.firstChild) h.removeChild(h.firstChild);
                var j = c(f.text).split("\n"), k = [], m;
                for (var n = 0, o = j.length; n < o; n++) m = q("tspan"), n && q(m, {
                    dy: i * x,
                    x: g.x
                }), m.appendChild(a._g.doc.createTextNode(j[n])), h.appendChild(m), k[n] = m;
            } else {
                k = h.getElementsByTagName("tspan");
                for (n = 0, o = k.length; n < o; n++) n ? q(k[n], {
                    dy: i * x,
                    x: g.x
                }) : q(k[0], {
                    dy: 0
                });
            }
            q(h, {
                x: g.x,
                y: g.y
            }), d._.dirty = 1;
            var p = d._getBBox(), r = g.y - (p.y + p.height / 2);
            r && a.is(r, "finite") && q(k[0], {
                dy: r
            });
        }
    }, z = function(b, c) {
        var d = 0, e = 0;
        this[0] = this.node = b, b.raphael = !0, this.id = a._oid++, b.raphaelid = this.id, 
        this.matrix = a.matrix(), this.realPath = null, this.paper = c, this.attrs = this.attrs || {}, 
        this._ = {
            transform: [],
            sx: 1,
            sy: 1,
            deg: 0,
            dx: 0,
            dy: 0,
            dirty: 1
        }, !c.bottom && (c.bottom = this), this.prev = c.top, c.top && (c.top.next = this), 
        c.top = this, this.next = null;
    }, A = a.el;
    z.prototype = A, A.constructor = z, a._engine.path = function(a, b) {
        var c = q("path");
        b.canvas && b.canvas.appendChild(c);
        var d = new z(c, b);
        d.type = "path", w(d, {
            fill: "none",
            stroke: "#000",
            path: a
        });
        return d;
    }, A.rotate = function(a, b, e) {
        if (this.removed) return this;
        a = c(a).split(j), a.length - 1 && (b = d(a[1]), e = d(a[2])), a = d(a[0]), e == null && (b = e);
        if (b == null || e == null) {
            var f = this.getBBox(1);
            b = f.x + f.width / 2, e = f.y + f.height / 2;
        }
        this.transform(this._.transform.concat([ [ "r", a, b, e ] ]));
        return this;
    }, A.scale = function(a, b, e, f) {
        if (this.removed) return this;
        a = c(a).split(j), a.length - 1 && (b = d(a[1]), e = d(a[2]), f = d(a[3])), a = d(a[0]), 
        b == null && (b = a), f == null && (e = f);
        if (e == null || f == null) var g = this.getBBox(1);
        e = e == null ? g.x + g.width / 2 : e, f = f == null ? g.y + g.height / 2 : f, this.transform(this._.transform.concat([ [ "s", a, b, e, f ] ]));
        return this;
    }, A.translate = function(a, b) {
        if (this.removed) return this;
        a = c(a).split(j), a.length - 1 && (b = d(a[1])), a = d(a[0]) || 0, b = +b || 0, 
        this.transform(this._.transform.concat([ [ "t", a, b ] ]));
        return this;
    }, A.transform = function(c) {
        var d = this._;
        if (c == null) return d.transform;
        a._extractTransform(this, c), this.clip && q(this.clip, {
            transform: this.matrix.invert()
        }), this.pattern && s(this), this.node && q(this.node, {
            transform: this.matrix
        });
        if (d.sx != 1 || d.sy != 1) {
            var e = this.attrs[b]("stroke-width") ? this.attrs["stroke-width"] : 1;
            this.attr({
                "stroke-width": e
            });
        }
        return this;
    }, A.hide = function() {
        !this.removed && this.paper.safari(this.node.style.display = "none");
        return this;
    }, A.show = function() {
        !this.removed && this.paper.safari(this.node.style.display = "");
        return this;
    }, A.remove = function() {
        if (!this.removed && !!this.node.parentNode) {
            var b = this.paper;
            b.__set__ && b.__set__.exclude(this), k.unbind("raphael.*.*." + this.id), this.gradient && b.defs.removeChild(this.gradient), 
            a._tear(this, b), this.node.parentNode.tagName.toLowerCase() == "a" ? this.node.parentNode.parentNode.removeChild(this.node.parentNode) : this.node.parentNode.removeChild(this.node);
            for (var c in this) this[c] = typeof this[c] == "function" ? a._removedFactory(c) : null;
            this.removed = !0;
        }
    }, A._getBBox = function() {
        if (this.node.style.display == "none") {
            this.show();
            var a = !0;
        }
        var b = {};
        try {
            b = this.node.getBBox();
        } catch (c) {} finally {
            b = b || {};
        }
        a && this.hide();
        return b;
    }, A.attr = function(c, d) {
        if (this.removed) return this;
        if (c == null) {
            var e = {};
            for (var f in this.attrs) this.attrs[b](f) && (e[f] = this.attrs[f]);
            e.gradient && e.fill == "none" && (e.fill = e.gradient) && delete e.gradient, e.transform = this._.transform;
            return e;
        }
        if (d == null && a.is(c, "string")) {
            if (c == "fill" && this.attrs.fill == "none" && this.attrs.gradient) return this.attrs.gradient;
            if (c == "transform") return this._.transform;
            var g = c.split(j), h = {};
            for (var i = 0, l = g.length; i < l; i++) c = g[i], c in this.attrs ? h[c] = this.attrs[c] : a.is(this.paper.customAttributes[c], "function") ? h[c] = this.paper.customAttributes[c].def : h[c] = a._availableAttrs[c];
            return l - 1 ? h : h[g[0]];
        }
        if (d == null && a.is(c, "array")) {
            h = {};
            for (i = 0, l = c.length; i < l; i++) h[c[i]] = this.attr(c[i]);
            return h;
        }
        if (d != null) {
            var m = {};
            m[c] = d;
        } else c != null && a.is(c, "object") && (m = c);
        for (var n in m) k("raphael.attr." + n + "." + this.id, this, m[n]);
        for (n in this.paper.customAttributes) if (this.paper.customAttributes[b](n) && m[b](n) && a.is(this.paper.customAttributes[n], "function")) {
            var o = this.paper.customAttributes[n].apply(this, [].concat(m[n]));
            this.attrs[n] = m[n];
            for (var p in o) o[b](p) && (m[p] = o[p]);
        }
        w(this, m);
        return this;
    }, A.toFront = function() {
        if (this.removed) return this;
        this.node.parentNode.tagName.toLowerCase() == "a" ? this.node.parentNode.parentNode.appendChild(this.node.parentNode) : this.node.parentNode.appendChild(this.node);
        var b = this.paper;
        b.top != this && a._tofront(this, b);
        return this;
    }, A.toBack = function() {
        if (this.removed) return this;
        var b = this.node.parentNode;
        b.tagName.toLowerCase() == "a" ? b.parentNode.insertBefore(this.node.parentNode, this.node.parentNode.parentNode.firstChild) : b.firstChild != this.node && b.insertBefore(this.node, this.node.parentNode.firstChild), 
        a._toback(this, this.paper);
        var c = this.paper;
        return this;
    }, A.insertAfter = function(b) {
        if (this.removed) return this;
        var c = b.node || b[b.length - 1].node;
        c.nextSibling ? c.parentNode.insertBefore(this.node, c.nextSibling) : c.parentNode.appendChild(this.node), 
        a._insertafter(this, b, this.paper);
        return this;
    }, A.insertBefore = function(b) {
        if (this.removed) return this;
        var c = b.node || b[0].node;
        c.parentNode.insertBefore(this.node, c), a._insertbefore(this, b, this.paper);
        return this;
    }, A.blur = function(b) {
        var c = this;
        if (+b !== 0) {
            var d = q("filter"), e = q("feGaussianBlur");
            c.attrs.blur = b, d.id = a.createUUID(), q(e, {
                stdDeviation: +b || 1.5
            }), d.appendChild(e), c.paper.defs.appendChild(d), c._blur = d, q(c.node, {
                filter: "url(#" + d.id + ")"
            });
        } else c._blur && (c._blur.parentNode.removeChild(c._blur), delete c._blur, delete c.attrs.blur), 
        c.node.removeAttribute("filter");
    }, a._engine.circle = function(a, b, c, d) {
        var e = q("circle");
        a.canvas && a.canvas.appendChild(e);
        var f = new z(e, a);
        f.attrs = {
            cx: b,
            cy: c,
            r: d,
            fill: "none",
            stroke: "#000"
        }, f.type = "circle", q(e, f.attrs);
        return f;
    }, a._engine.rect = function(a, b, c, d, e, f) {
        var g = q("rect");
        a.canvas && a.canvas.appendChild(g);
        var h = new z(g, a);
        h.attrs = {
            x: b,
            y: c,
            width: d,
            height: e,
            r: f || 0,
            rx: f || 0,
            ry: f || 0,
            fill: "none",
            stroke: "#000"
        }, h.type = "rect", q(g, h.attrs);
        return h;
    }, a._engine.ellipse = function(a, b, c, d, e) {
        var f = q("ellipse");
        a.canvas && a.canvas.appendChild(f);
        var g = new z(f, a);
        g.attrs = {
            cx: b,
            cy: c,
            rx: d,
            ry: e,
            fill: "none",
            stroke: "#000"
        }, g.type = "ellipse", q(f, g.attrs);
        return g;
    }, a._engine.image = function(a, b, c, d, e, f) {
        var g = q("image");
        q(g, {
            x: c,
            y: d,
            width: e,
            height: f,
            preserveAspectRatio: "none"
        }), g.setAttributeNS(n, "href", b), a.canvas && a.canvas.appendChild(g);
        var h = new z(g, a);
        h.attrs = {
            x: c,
            y: d,
            width: e,
            height: f,
            src: b
        }, h.type = "image";
        return h;
    }, a._engine.text = function(b, c, d, e) {
        var f = q("text");
        b.canvas && b.canvas.appendChild(f);
        var g = new z(f, b);
        g.attrs = {
            x: c,
            y: d,
            "text-anchor": "middle",
            text: e,
            font: a._availableAttrs.font,
            stroke: "none",
            fill: "#000"
        }, g.type = "text", w(g, g.attrs);
        return g;
    }, a._engine.setSize = function(a, b) {
        this.width = a || this.width, this.height = b || this.height, this.canvas.setAttribute("width", this.width), 
        this.canvas.setAttribute("height", this.height), this._viewBox && this.setViewBox.apply(this, this._viewBox);
        return this;
    }, a._engine.create = function() {
        var b = a._getContainer.apply(0, arguments), c = b && b.container, d = b.x, e = b.y, f = b.width, g = b.height;
        if (!c) throw new Error("SVG container not found.");
        var h = q("svg"), i = "overflow:hidden;", j;
        d = d || 0, e = e || 0, f = f || 512, g = g || 342, q(h, {
            height: g,
            version: 1.1,
            width: f,
            xmlns: "http://www.w3.org/2000/svg"
        }), c == 1 ? (h.style.cssText = i + "position:absolute;left:" + d + "px;top:" + e + "px", 
        a._g.doc.body.appendChild(h), j = 1) : (h.style.cssText = i + "position:relative", 
        c.firstChild ? c.insertBefore(h, c.firstChild) : c.appendChild(h)), c = new a._Paper(), 
        c.width = f, c.height = g, c.canvas = h, c.clear(), c._left = c._top = 0, j && (c.renderfix = function() {}), 
        c.renderfix();
        return c;
    }, a._engine.setViewBox = function(a, b, c, d, e) {
        k("raphael.setViewBox", this, this._viewBox, [ a, b, c, d, e ]);
        var f = g(c / this.width, d / this.height), h = this.top, i = e ? "meet" : "xMinYMin", j, l;
        a == null ? (this._vbSize && (f = 1), delete this._vbSize, j = "0 0 " + this.width + m + this.height) : (this._vbSize = f, 
        j = a + m + b + m + c + m + d), q(this.canvas, {
            viewBox: j,
            preserveAspectRatio: i
        });
        while (f && h) l = "stroke-width" in h.attrs ? h.attrs["stroke-width"] : 1, h.attr({
            "stroke-width": l
        }), h._.dirty = 1, h._.dirtyT = 1, h = h.prev;
        this._viewBox = [ a, b, c, d, !!e ];
        return this;
    }, a.prototype.renderfix = function() {
        var a = this.canvas, b = a.style, c;
        try {
            c = a.getScreenCTM() || a.createSVGMatrix();
        } catch (d) {
            c = a.createSVGMatrix();
        }
        var e = -c.e % 1, f = -c.f % 1;
        if (e || f) e && (this._left = (this._left + e) % 1, b.left = this._left + "px"), 
        f && (this._top = (this._top + f) % 1, b.top = this._top + "px");
    }, a.prototype.clear = function() {
        a.eve("raphael.clear", this);
        var b = this.canvas;
        while (b.firstChild) b.removeChild(b.firstChild);
        this.bottom = this.top = null, (this.desc = q("desc")).appendChild(a._g.doc.createTextNode("Created with Raphaël " + a.version)), 
        b.appendChild(this.desc), b.appendChild(this.defs = q("defs"));
    }, a.prototype.remove = function() {
        k("raphael.remove", this), this.canvas.parentNode && this.canvas.parentNode.removeChild(this.canvas);
        for (var b in this) this[b] = typeof this[b] == "function" ? a._removedFactory(b) : null;
    };
    var B = a.st;
    for (var C in A) A[b](C) && !B[b](C) && (B[C] = function(a) {
        return function() {
            var b = arguments;
            return this.forEach(function(c) {
                c[a].apply(c, b);
            });
        };
    }(C));
}(window.Raphael), window.Raphael.vml && function(a) {
    var b = "hasOwnProperty", c = String, d = parseFloat, e = Math, f = e.round, g = e.max, h = e.min, i = e.abs, j = "fill", k = /[, ]+/, l = a.eve, m = " progid:DXImageTransform.Microsoft", n = " ", o = "", p = {
        M: "m",
        L: "l",
        C: "c",
        Z: "x",
        m: "t",
        l: "r",
        c: "v",
        z: "x"
    }, q = /([clmz]),?([^clmz]*)/gi, r = / progid:\S+Blur\([^\)]+\)/g, s = /-?[^,\s-]+/g, t = "position:absolute;left:0;top:0;width:1px;height:1px", u = 21600, v = {
        path: 1,
        rect: 1,
        image: 1
    }, w = {
        circle: 1,
        ellipse: 1
    }, x = function(b) {
        var d = /[ahqstv]/gi, e = a._pathToAbsolute;
        c(b).match(d) && (e = a._path2curve), d = /[clmz]/g;
        if (e == a._pathToAbsolute && !c(b).match(d)) {
            var g = c(b).replace(q, function(a, b, c) {
                var d = [], e = b.toLowerCase() == "m", g = p[b];
                c.replace(s, function(a) {
                    e && d.length == 2 && (g += d + p[b == "m" ? "l" : "L"], d = []), d.push(f(a * u));
                });
                return g + d;
            });
            return g;
        }
        var h = e(b), i, j;
        g = [];
        for (var k = 0, l = h.length; k < l; k++) {
            i = h[k], j = h[k][0].toLowerCase(), j == "z" && (j = "x");
            for (var m = 1, r = i.length; m < r; m++) j += f(i[m] * u) + (m != r - 1 ? "," : o);
            g.push(j);
        }
        return g.join(n);
    }, y = function(b, c, d) {
        var e = a.matrix();
        e.rotate(-b, .5, .5);
        return {
            dx: e.x(c, d),
            dy: e.y(c, d)
        };
    }, z = function(a, b, c, d, e, f) {
        var g = a._, h = a.matrix, k = g.fillpos, l = a.node, m = l.style, o = 1, p = "", q, r = u / b, s = u / c;
        m.visibility = "hidden";
        if (!!b && !!c) {
            l.coordsize = i(r) + n + i(s), m.rotation = f * (b * c < 0 ? -1 : 1);
            if (f) {
                var t = y(f, d, e);
                d = t.dx, e = t.dy;
            }
            b < 0 && (p += "x"), c < 0 && (p += " y") && (o = -1), m.flip = p, l.coordorigin = d * -r + n + e * -s;
            if (k || g.fillsize) {
                var v = l.getElementsByTagName(j);
                v = v && v[0], l.removeChild(v), k && (t = y(f, h.x(k[0], k[1]), h.y(k[0], k[1])), 
                v.position = t.dx * o + n + t.dy * o), g.fillsize && (v.size = g.fillsize[0] * i(b) + n + g.fillsize[1] * i(c)), 
                l.appendChild(v);
            }
            m.visibility = "visible";
        }
    };
    a.toString = function() {
        return "Your browser doesn’t support SVG. Falling down to VML.\nYou are running Raphaël " + this.version;
    };
    var A = function(a, b, d) {
        var e = c(b).toLowerCase().split("-"), f = d ? "end" : "start", g = e.length, h = "classic", i = "medium", j = "medium";
        while (g--) switch (e[g]) {
          case "block":
          case "classic":
          case "oval":
          case "diamond":
          case "open":
          case "none":
            h = e[g];
            break;

          case "wide":
          case "narrow":
            j = e[g];
            break;

          case "long":
          case "short":
            i = e[g];
        }
        var k = a.node.getElementsByTagName("stroke")[0];
        k[f + "arrow"] = h, k[f + "arrowlength"] = i, k[f + "arrowwidth"] = j;
    }, B = function(e, i) {
        e.attrs = e.attrs || {};
        var l = e.node, m = e.attrs, p = l.style, q, r = v[e.type] && (i.x != m.x || i.y != m.y || i.width != m.width || i.height != m.height || i.cx != m.cx || i.cy != m.cy || i.rx != m.rx || i.ry != m.ry || i.r != m.r), s = w[e.type] && (m.cx != i.cx || m.cy != i.cy || m.r != i.r || m.rx != i.rx || m.ry != i.ry), t = e;
        for (var y in i) i[b](y) && (m[y] = i[y]);
        r && (m.path = a._getPath[e.type](e), e._.dirty = 1), i.href && (l.href = i.href), 
        i.title && (l.title = i.title), i.target && (l.target = i.target), i.cursor && (p.cursor = i.cursor), 
        "blur" in i && e.blur(i.blur);
        if (i.path && e.type == "path" || r) l.path = x(~c(m.path).toLowerCase().indexOf("r") ? a._pathToAbsolute(m.path) : m.path), 
        e.type == "image" && (e._.fillpos = [ m.x, m.y ], e._.fillsize = [ m.width, m.height ], 
        z(e, 1, 1, 0, 0, 0));
        "transform" in i && e.transform(i.transform);
        if (s) {
            var B = +m.cx, D = +m.cy, E = +m.rx || +m.r || 0, G = +m.ry || +m.r || 0;
            l.path = a.format("ar{0},{1},{2},{3},{4},{1},{4},{1}x", f((B - E) * u), f((D - G) * u), f((B + E) * u), f((D + G) * u), f(B * u));
        }
        if ("clip-rect" in i) {
            var H = c(i["clip-rect"]).split(k);
            if (H.length == 4) {
                H[2] = +H[2] + +H[0], H[3] = +H[3] + +H[1];
                var I = l.clipRect || a._g.doc.createElement("div"), J = I.style;
                J.clip = a.format("rect({1}px {2}px {3}px {0}px)", H), l.clipRect || (J.position = "absolute", 
                J.top = 0, J.left = 0, J.width = e.paper.width + "px", J.height = e.paper.height + "px", 
                l.parentNode.insertBefore(I, l), I.appendChild(l), l.clipRect = I);
            }
            i["clip-rect"] || l.clipRect && (l.clipRect.style.clip = "auto");
        }
        if (e.textpath) {
            var K = e.textpath.style;
            i.font && (K.font = i.font), i["font-family"] && (K.fontFamily = '"' + i["font-family"].split(",")[0].replace(/^['"]+|['"]+$/g, o) + '"'), 
            i["font-size"] && (K.fontSize = i["font-size"]), i["font-weight"] && (K.fontWeight = i["font-weight"]), 
            i["font-style"] && (K.fontStyle = i["font-style"]);
        }
        "arrow-start" in i && A(t, i["arrow-start"]), "arrow-end" in i && A(t, i["arrow-end"], 1);
        if (i.opacity != null || i["stroke-width"] != null || i.fill != null || i.src != null || i.stroke != null || i["stroke-width"] != null || i["stroke-opacity"] != null || i["fill-opacity"] != null || i["stroke-dasharray"] != null || i["stroke-miterlimit"] != null || i["stroke-linejoin"] != null || i["stroke-linecap"] != null) {
            var L = l.getElementsByTagName(j), M = !1;
            L = L && L[0], !L && (M = L = F(j)), e.type == "image" && i.src && (L.src = i.src), 
            i.fill && (L.on = !0);
            if (L.on == null || i.fill == "none" || i.fill === null) L.on = !1;
            if (L.on && i.fill) {
                var N = c(i.fill).match(a._ISURL);
                if (N) {
                    L.parentNode == l && l.removeChild(L), L.rotate = !0, L.src = N[1], L.type = "tile";
                    var O = e.getBBox(1);
                    L.position = O.x + n + O.y, e._.fillpos = [ O.x, O.y ], a._preload(N[1], function() {
                        e._.fillsize = [ this.offsetWidth, this.offsetHeight ];
                    });
                } else L.color = a.getRGB(i.fill).hex, L.src = o, L.type = "solid", a.getRGB(i.fill).error && (t.type in {
                    circle: 1,
                    ellipse: 1
                } || c(i.fill).charAt() != "r") && C(t, i.fill, L) && (m.fill = "none", m.gradient = i.fill, 
                L.rotate = !1);
            }
            if ("fill-opacity" in i || "opacity" in i) {
                var P = ((+m["fill-opacity"] + 1 || 2) - 1) * ((+m.opacity + 1 || 2) - 1) * ((+a.getRGB(i.fill).o + 1 || 2) - 1);
                P = h(g(P, 0), 1), L.opacity = P, L.src && (L.color = "none");
            }
            l.appendChild(L);
            var Q = l.getElementsByTagName("stroke") && l.getElementsByTagName("stroke")[0], T = !1;
            !Q && (T = Q = F("stroke"));
            if (i.stroke && i.stroke != "none" || i["stroke-width"] || i["stroke-opacity"] != null || i["stroke-dasharray"] || i["stroke-miterlimit"] || i["stroke-linejoin"] || i["stroke-linecap"]) Q.on = !0;
            (i.stroke == "none" || i.stroke === null || Q.on == null || i.stroke == 0 || i["stroke-width"] == 0) && (Q.on = !1);
            var U = a.getRGB(i.stroke);
            Q.on && i.stroke && (Q.color = U.hex), P = ((+m["stroke-opacity"] + 1 || 2) - 1) * ((+m.opacity + 1 || 2) - 1) * ((+U.o + 1 || 2) - 1);
            var V = (d(i["stroke-width"]) || 1) * .75;
            P = h(g(P, 0), 1), i["stroke-width"] == null && (V = m["stroke-width"]), i["stroke-width"] && (Q.weight = V), 
            V && V < 1 && (P *= V) && (Q.weight = 1), Q.opacity = P, i["stroke-linejoin"] && (Q.joinstyle = i["stroke-linejoin"] || "miter"), 
            Q.miterlimit = i["stroke-miterlimit"] || 8, i["stroke-linecap"] && (Q.endcap = i["stroke-linecap"] == "butt" ? "flat" : i["stroke-linecap"] == "square" ? "square" : "round");
            if (i["stroke-dasharray"]) {
                var W = {
                    "-": "shortdash",
                    ".": "shortdot",
                    "-.": "shortdashdot",
                    "-..": "shortdashdotdot",
                    ". ": "dot",
                    "- ": "dash",
                    "--": "longdash",
                    "- .": "dashdot",
                    "--.": "longdashdot",
                    "--..": "longdashdotdot"
                };
                Q.dashstyle = W[b](i["stroke-dasharray"]) ? W[i["stroke-dasharray"]] : o;
            }
            T && l.appendChild(Q);
        }
        if (t.type == "text") {
            t.paper.canvas.style.display = o;
            var X = t.paper.span, Y = 100, Z = m.font && m.font.match(/\d+(?:\.\d*)?(?=px)/);
            p = X.style, m.font && (p.font = m.font), m["font-family"] && (p.fontFamily = m["font-family"]), 
            m["font-weight"] && (p.fontWeight = m["font-weight"]), m["font-style"] && (p.fontStyle = m["font-style"]), 
            Z = d(m["font-size"] || Z && Z[0]) || 10, p.fontSize = Z * Y + "px", t.textpath.string && (X.innerHTML = c(t.textpath.string).replace(/</g, "&#60;").replace(/&/g, "&#38;").replace(/\n/g, "<br>"));
            var $ = X.getBoundingClientRect();
            t.W = m.w = ($.right - $.left) / Y, t.H = m.h = ($.bottom - $.top) / Y, t.X = m.x, 
            t.Y = m.y + t.H / 2, ("x" in i || "y" in i) && (t.path.v = a.format("m{0},{1}l{2},{1}", f(m.x * u), f(m.y * u), f(m.x * u) + 1));
            var _ = [ "x", "y", "text", "font", "font-family", "font-weight", "font-style", "font-size" ];
            for (var ba = 0, bb = _.length; ba < bb; ba++) if (_[ba] in i) {
                t._.dirty = 1;
                break;
            }
            switch (m["text-anchor"]) {
              case "start":
                t.textpath.style["v-text-align"] = "left", t.bbx = t.W / 2;
                break;

              case "end":
                t.textpath.style["v-text-align"] = "right", t.bbx = -t.W / 2;
                break;

              default:
                t.textpath.style["v-text-align"] = "center", t.bbx = 0;
            }
            t.textpath.style["v-text-kern"] = !0;
        }
    }, C = function(b, f, g) {
        b.attrs = b.attrs || {};
        var h = b.attrs, i = Math.pow, j, k, l = "linear", m = ".5 .5";
        b.attrs.gradient = f, f = c(f).replace(a._radial_gradient, function(a, b, c) {
            l = "radial", b && c && (b = d(b), c = d(c), i(b - .5, 2) + i(c - .5, 2) > .25 && (c = e.sqrt(.25 - i(b - .5, 2)) * ((c > .5) * 2 - 1) + .5), 
            m = b + n + c);
            return o;
        }), f = f.split(/\s*\-\s*/);
        if (l == "linear") {
            var p = f.shift();
            p = -d(p);
            if (isNaN(p)) return null;
        }
        var q = a._parseDots(f);
        if (!q) return null;
        b = b.shape || b.node;
        if (q.length) {
            b.removeChild(g), g.on = !0, g.method = "none", g.color = q[0].color, g.color2 = q[q.length - 1].color;
            var r = [];
            for (var s = 0, t = q.length; s < t; s++) q[s].offset && r.push(q[s].offset + n + q[s].color);
            g.colors = r.length ? r.join() : "0% " + g.color, l == "radial" ? (g.type = "gradientTitle", 
            g.focus = "100%", g.focussize = "0 0", g.focusposition = m, g.angle = 0) : (g.type = "gradient", 
            g.angle = (270 - p) % 360), b.appendChild(g);
        }
        return 1;
    }, D = function(b, c) {
        this[0] = this.node = b, b.raphael = !0, this.id = a._oid++, b.raphaelid = this.id, 
        this.X = 0, this.Y = 0, this.attrs = {}, this.paper = c, this.matrix = a.matrix(), 
        this._ = {
            transform: [],
            sx: 1,
            sy: 1,
            dx: 0,
            dy: 0,
            deg: 0,
            dirty: 1,
            dirtyT: 1
        }, !c.bottom && (c.bottom = this), this.prev = c.top, c.top && (c.top.next = this), 
        c.top = this, this.next = null;
    }, E = a.el;
    D.prototype = E, E.constructor = D, E.transform = function(b) {
        if (b == null) return this._.transform;
        var d = this.paper._viewBoxShift, e = d ? "s" + [ d.scale, d.scale ] + "-1-1t" + [ d.dx, d.dy ] : o, f;
        d && (f = b = c(b).replace(/\.{3}|\u2026/g, this._.transform || o)), a._extractTransform(this, e + b);
        var g = this.matrix.clone(), h = this.skew, i = this.node, j, k = ~c(this.attrs.fill).indexOf("-"), l = !c(this.attrs.fill).indexOf("url(");
        g.translate(-.5, -.5);
        if (l || k || this.type == "image") {
            h.matrix = "1 0 0 1", h.offset = "0 0", j = g.split();
            if (k && j.noRotation || !j.isSimple) {
                i.style.filter = g.toFilter();
                var m = this.getBBox(), p = this.getBBox(1), q = m.x - p.x, r = m.y - p.y;
                i.coordorigin = q * -u + n + r * -u, z(this, 1, 1, q, r, 0);
            } else i.style.filter = o, z(this, j.scalex, j.scaley, j.dx, j.dy, j.rotate);
        } else i.style.filter = o, h.matrix = c(g), h.offset = g.offset();
        f && (this._.transform = f);
        return this;
    }, E.rotate = function(a, b, e) {
        if (this.removed) return this;
        if (a != null) {
            a = c(a).split(k), a.length - 1 && (b = d(a[1]), e = d(a[2])), a = d(a[0]), e == null && (b = e);
            if (b == null || e == null) {
                var f = this.getBBox(1);
                b = f.x + f.width / 2, e = f.y + f.height / 2;
            }
            this._.dirtyT = 1, this.transform(this._.transform.concat([ [ "r", a, b, e ] ]));
            return this;
        }
    }, E.translate = function(a, b) {
        if (this.removed) return this;
        a = c(a).split(k), a.length - 1 && (b = d(a[1])), a = d(a[0]) || 0, b = +b || 0, 
        this._.bbox && (this._.bbox.x += a, this._.bbox.y += b), this.transform(this._.transform.concat([ [ "t", a, b ] ]));
        return this;
    }, E.scale = function(a, b, e, f) {
        if (this.removed) return this;
        a = c(a).split(k), a.length - 1 && (b = d(a[1]), e = d(a[2]), f = d(a[3]), isNaN(e) && (e = null), 
        isNaN(f) && (f = null)), a = d(a[0]), b == null && (b = a), f == null && (e = f);
        if (e == null || f == null) var g = this.getBBox(1);
        e = e == null ? g.x + g.width / 2 : e, f = f == null ? g.y + g.height / 2 : f, this.transform(this._.transform.concat([ [ "s", a, b, e, f ] ])), 
        this._.dirtyT = 1;
        return this;
    }, E.hide = function() {
        !this.removed && (this.node.style.display = "none");
        return this;
    }, E.show = function() {
        !this.removed && (this.node.style.display = o);
        return this;
    }, E._getBBox = function() {
        if (this.removed) return {};
        return {
            x: this.X + (this.bbx || 0) - this.W / 2,
            y: this.Y - this.H,
            width: this.W,
            height: this.H
        };
    }, E.remove = function() {
        if (!this.removed && !!this.node.parentNode) {
            this.paper.__set__ && this.paper.__set__.exclude(this), a.eve.unbind("raphael.*.*." + this.id), 
            a._tear(this, this.paper), this.node.parentNode.removeChild(this.node), this.shape && this.shape.parentNode.removeChild(this.shape);
            for (var b in this) this[b] = typeof this[b] == "function" ? a._removedFactory(b) : null;
            this.removed = !0;
        }
    }, E.attr = function(c, d) {
        if (this.removed) return this;
        if (c == null) {
            var e = {};
            for (var f in this.attrs) this.attrs[b](f) && (e[f] = this.attrs[f]);
            e.gradient && e.fill == "none" && (e.fill = e.gradient) && delete e.gradient, e.transform = this._.transform;
            return e;
        }
        if (d == null && a.is(c, "string")) {
            if (c == j && this.attrs.fill == "none" && this.attrs.gradient) return this.attrs.gradient;
            var g = c.split(k), h = {};
            for (var i = 0, m = g.length; i < m; i++) c = g[i], c in this.attrs ? h[c] = this.attrs[c] : a.is(this.paper.customAttributes[c], "function") ? h[c] = this.paper.customAttributes[c].def : h[c] = a._availableAttrs[c];
            return m - 1 ? h : h[g[0]];
        }
        if (this.attrs && d == null && a.is(c, "array")) {
            h = {};
            for (i = 0, m = c.length; i < m; i++) h[c[i]] = this.attr(c[i]);
            return h;
        }
        var n;
        d != null && (n = {}, n[c] = d), d == null && a.is(c, "object") && (n = c);
        for (var o in n) l("raphael.attr." + o + "." + this.id, this, n[o]);
        if (n) {
            for (o in this.paper.customAttributes) if (this.paper.customAttributes[b](o) && n[b](o) && a.is(this.paper.customAttributes[o], "function")) {
                var p = this.paper.customAttributes[o].apply(this, [].concat(n[o]));
                this.attrs[o] = n[o];
                for (var q in p) p[b](q) && (n[q] = p[q]);
            }
            n.text && this.type == "text" && (this.textpath.string = n.text), B(this, n);
        }
        return this;
    }, E.toFront = function() {
        !this.removed && this.node.parentNode.appendChild(this.node), this.paper && this.paper.top != this && a._tofront(this, this.paper);
        return this;
    }, E.toBack = function() {
        if (this.removed) return this;
        this.node.parentNode.firstChild != this.node && (this.node.parentNode.insertBefore(this.node, this.node.parentNode.firstChild), 
        a._toback(this, this.paper));
        return this;
    }, E.insertAfter = function(b) {
        if (this.removed) return this;
        b.constructor == a.st.constructor && (b = b[b.length - 1]), b.node.nextSibling ? b.node.parentNode.insertBefore(this.node, b.node.nextSibling) : b.node.parentNode.appendChild(this.node), 
        a._insertafter(this, b, this.paper);
        return this;
    }, E.insertBefore = function(b) {
        if (this.removed) return this;
        b.constructor == a.st.constructor && (b = b[0]), b.node.parentNode.insertBefore(this.node, b.node), 
        a._insertbefore(this, b, this.paper);
        return this;
    }, E.blur = function(b) {
        var c = this.node.runtimeStyle, d = c.filter;
        d = d.replace(r, o), +b !== 0 ? (this.attrs.blur = b, c.filter = d + n + m + ".Blur(pixelradius=" + (+b || 1.5) + ")", 
        c.margin = a.format("-{0}px 0 0 -{0}px", f(+b || 1.5))) : (c.filter = d, c.margin = 0, 
        delete this.attrs.blur);
    }, a._engine.path = function(a, b) {
        var c = F("shape");
        c.style.cssText = t, c.coordsize = u + n + u, c.coordorigin = b.coordorigin;
        var d = new D(c, b), e = {
            fill: "none",
            stroke: "#000"
        };
        a && (e.path = a), d.type = "path", d.path = [], d.Path = o, B(d, e), b.canvas.appendChild(c);
        var f = F("skew");
        f.on = !0, c.appendChild(f), d.skew = f, d.transform(o);
        return d;
    }, a._engine.rect = function(b, c, d, e, f, g) {
        var h = a._rectPath(c, d, e, f, g), i = b.path(h), j = i.attrs;
        i.X = j.x = c, i.Y = j.y = d, i.W = j.width = e, i.H = j.height = f, j.r = g, j.path = h, 
        i.type = "rect";
        return i;
    }, a._engine.ellipse = function(a, b, c, d, e) {
        var f = a.path(), g = f.attrs;
        f.X = b - d, f.Y = c - e, f.W = d * 2, f.H = e * 2, f.type = "ellipse", B(f, {
            cx: b,
            cy: c,
            rx: d,
            ry: e
        });
        return f;
    }, a._engine.circle = function(a, b, c, d) {
        var e = a.path(), f = e.attrs;
        e.X = b - d, e.Y = c - d, e.W = e.H = d * 2, e.type = "circle", B(e, {
            cx: b,
            cy: c,
            r: d
        });
        return e;
    }, a._engine.image = function(b, c, d, e, f, g) {
        var h = a._rectPath(d, e, f, g), i = b.path(h).attr({
            stroke: "none"
        }), k = i.attrs, l = i.node, m = l.getElementsByTagName(j)[0];
        k.src = c, i.X = k.x = d, i.Y = k.y = e, i.W = k.width = f, i.H = k.height = g, 
        k.path = h, i.type = "image", m.parentNode == l && l.removeChild(m), m.rotate = !0, 
        m.src = c, m.type = "tile", i._.fillpos = [ d, e ], i._.fillsize = [ f, g ], l.appendChild(m), 
        z(i, 1, 1, 0, 0, 0);
        return i;
    }, a._engine.text = function(b, d, e, g) {
        var h = F("shape"), i = F("path"), j = F("textpath");
        d = d || 0, e = e || 0, g = g || "", i.v = a.format("m{0},{1}l{2},{1}", f(d * u), f(e * u), f(d * u) + 1), 
        i.textpathok = !0, j.string = c(g), j.on = !0, h.style.cssText = t, h.coordsize = u + n + u, 
        h.coordorigin = "0 0";
        var k = new D(h, b), l = {
            fill: "#000",
            stroke: "none",
            font: a._availableAttrs.font,
            text: g
        };
        k.shape = h, k.path = i, k.textpath = j, k.type = "text", k.attrs.text = c(g), k.attrs.x = d, 
        k.attrs.y = e, k.attrs.w = 1, k.attrs.h = 1, B(k, l), h.appendChild(j), h.appendChild(i), 
        b.canvas.appendChild(h);
        var m = F("skew");
        m.on = !0, h.appendChild(m), k.skew = m, k.transform(o);
        return k;
    }, a._engine.setSize = function(b, c) {
        var d = this.canvas.style;
        this.width = b, this.height = c, b == +b && (b += "px"), c == +c && (c += "px"), 
        d.width = b, d.height = c, d.clip = "rect(0 " + b + " " + c + " 0)", this._viewBox && a._engine.setViewBox.apply(this, this._viewBox);
        return this;
    }, a._engine.setViewBox = function(b, c, d, e, f) {
        a.eve("raphael.setViewBox", this, this._viewBox, [ b, c, d, e, f ]);
        var h = this.width, i = this.height, j = 1 / g(d / h, e / i), k, l;
        f && (k = i / e, l = h / d, d * k < h && (b -= (h - d * k) / 2 / k), e * l < i && (c -= (i - e * l) / 2 / l)), 
        this._viewBox = [ b, c, d, e, !!f ], this._viewBoxShift = {
            dx: -b,
            dy: -c,
            scale: j
        }, this.forEach(function(a) {
            a.transform("...");
        });
        return this;
    };
    var F;
    a._engine.initWin = function(a) {
        var b = a.document;
        b.createStyleSheet().addRule(".rvml", "behavior:url(#default#VML)");
        try {
            !b.namespaces.rvml && b.namespaces.add("rvml", "urn:schemas-microsoft-com:vml"), 
            F = function(a) {
                return b.createElement("<rvml:" + a + ' class="rvml">');
            };
        } catch (c) {
            F = function(a) {
                return b.createElement("<" + a + ' xmlns="urn:schemas-microsoft.com:vml" class="rvml">');
            };
        }
    }, a._engine.initWin(a._g.win), a._engine.create = function() {
        var b = a._getContainer.apply(0, arguments), c = b.container, d = b.height, e, f = b.width, g = b.x, h = b.y;
        if (!c) throw new Error("VML container not found.");
        var i = new a._Paper(), j = i.canvas = a._g.doc.createElement("div"), k = j.style;
        g = g || 0, h = h || 0, f = f || 512, d = d || 342, i.width = f, i.height = d, f == +f && (f += "px"), 
        d == +d && (d += "px"), i.coordsize = u * 1e3 + n + u * 1e3, i.coordorigin = "0 0", 
        i.span = a._g.doc.createElement("span"), i.span.style.cssText = "position:absolute;left:-9999em;top:-9999em;padding:0;margin:0;line-height:1;", 
        j.appendChild(i.span), k.cssText = a.format("top:0;left:0;width:{0};height:{1};display:inline-block;position:relative;clip:rect(0 {0} {1} 0);overflow:hidden", f, d), 
        c == 1 ? (a._g.doc.body.appendChild(j), k.left = g + "px", k.top = h + "px", k.position = "absolute") : c.firstChild ? c.insertBefore(j, c.firstChild) : c.appendChild(j), 
        i.renderfix = function() {};
        return i;
    }, a.prototype.clear = function() {
        a.eve("raphael.clear", this), this.canvas.innerHTML = o, this.span = a._g.doc.createElement("span"), 
        this.span.style.cssText = "position:absolute;left:-9999em;top:-9999em;padding:0;margin:0;line-height:1;display:inline;", 
        this.canvas.appendChild(this.span), this.bottom = this.top = null;
    }, a.prototype.remove = function() {
        a.eve("raphael.remove", this), this.canvas.parentNode.removeChild(this.canvas);
        for (var b in this) this[b] = typeof this[b] == "function" ? a._removedFactory(b) : null;
        return !0;
    };
    var G = a.st;
    for (var H in E) E[b](H) && !G[b](H) && (G[H] = function(a) {
        return function() {
            var b = arguments;
            return this.forEach(function(c) {
                c[a].apply(c, b);
            });
        };
    }(H));
}(window.Raphael);

(function() {
    var a, b, c, d, e = [].slice, f = function(a, b) {
        return function() {
            return a.apply(b, arguments);
        };
    }, g = {}.hasOwnProperty, h = function(a, b) {
        function c() {
            this.constructor = a;
        }
        for (var d in b) g.call(b, d) && (a[d] = b[d]);
        return c.prototype = b.prototype, a.prototype = new c(), a.__super__ = b.prototype, 
        a;
    }, i = [].indexOf || function(a) {
        for (var b = 0, c = this.length; c > b; b++) if (b in this && this[b] === a) return b;
        return -1;
    };
    b = window.Morris = {}, a = jQuery, b.EventEmitter = function() {
        function a() {}
        return a.prototype.on = function(a, b) {
            return null == this.handlers && (this.handlers = {}), null == this.handlers[a] && (this.handlers[a] = []), 
            this.handlers[a].push(b), this;
        }, a.prototype.fire = function() {
            var a, b, c, d, f, g, h;
            if (c = arguments[0], a = 2 <= arguments.length ? e.call(arguments, 1) : [], null != this.handlers && null != this.handlers[c]) {
                for (g = this.handlers[c], h = [], d = 0, f = g.length; f > d; d++) b = g[d], h.push(b.apply(null, a));
                return h;
            }
        }, a;
    }(), b.commas = function(a) {
        var b, c, d, e;
        return null != a ? (d = 0 > a ? "-" : "", b = Math.abs(a), c = Math.floor(b).toFixed(0), 
        d += c.replace(/(?=(?:\d{3})+$)(?!^)/g, ","), e = b.toString(), e.length > c.length && (d += e.slice(c.length)), 
        d) : "-";
    }, b.pad2 = function(a) {
        return (10 > a ? "0" : "") + a;
    }, b.Grid = function(c) {
        function d(b) {
            this.resizeHandler = f(this.resizeHandler, this);
            var c = this;
            if (this.el = "string" == typeof b.element ? a(document.getElementById(b.element)) : a(b.element), 
            null == this.el || 0 === this.el.length) throw new Error("Graph container element not found");
            "static" === this.el.css("position") && this.el.css("position", "relative"), this.options = a.extend({}, this.gridDefaults, this.defaults || {}, b), 
            "string" == typeof this.options.units && (this.options.postUnits = b.units), this.raphael = new Raphael(this.el[0]), 
            this.elementWidth = null, this.elementHeight = null, this.dirty = !1, this.selectFrom = null, 
            this.init && this.init(), this.setData(this.options.data), this.el.bind("mousemove", function(a) {
                var b, d, e, f, g;
                return d = c.el.offset(), g = a.pageX - d.left, c.selectFrom ? (b = c.data[c.hitTest(Math.min(g, c.selectFrom))]._x, 
                e = c.data[c.hitTest(Math.max(g, c.selectFrom))]._x, f = e - b, c.selectionRect.attr({
                    x: b,
                    width: f
                })) : c.fire("hovermove", g, a.pageY - d.top);
            }), this.el.bind("mouseleave", function() {
                return c.selectFrom && (c.selectionRect.hide(), c.selectFrom = null), c.fire("hoverout");
            }), this.el.bind("touchstart touchmove touchend", function(a) {
                var b, d;
                return d = a.originalEvent.touches[0] || a.originalEvent.changedTouches[0], b = c.el.offset(), 
                c.fire("hovermove", d.pageX - b.left, d.pageY - b.top);
            }), this.el.bind("click", function(a) {
                var b;
                return b = c.el.offset(), c.fire("gridclick", a.pageX - b.left, a.pageY - b.top);
            }), this.options.rangeSelect && (this.selectionRect = this.raphael.rect(0, 0, 0, this.el.innerHeight()).attr({
                fill: this.options.rangeSelectColor,
                stroke: !1
            }).toBack().hide(), this.el.bind("mousedown", function(a) {
                var b;
                return b = c.el.offset(), c.startRange(a.pageX - b.left);
            }), this.el.bind("mouseup", function(a) {
                var b;
                return b = c.el.offset(), c.endRange(a.pageX - b.left), c.fire("hovermove", a.pageX - b.left, a.pageY - b.top);
            })), this.options.resize && a(window).bind("resize", function() {
                return null != c.timeoutId && window.clearTimeout(c.timeoutId), c.timeoutId = window.setTimeout(c.resizeHandler, 100);
            }), this.el.css("-webkit-tap-highlight-color", "rgba(0,0,0,0)"), this.postInit && this.postInit();
        }
        return h(d, c), d.prototype.gridDefaults = {
            dateFormat: null,
            axes: !0,
            grid: !0,
            gridLineColor: "#aaa",
            gridStrokeWidth: .5,
            gridTextColor: "#888",
            gridTextSize: 12,
            gridTextFamily: "sans-serif",
            gridTextWeight: "normal",
            hideHover: !1,
            yLabelFormat: null,
            xLabelAngle: 0,
            numLines: 5,
            padding: 25,
            parseTime: !0,
            postUnits: "",
            preUnits: "",
            ymax: "auto",
            ymin: "auto 0",
            goals: [],
            goalStrokeWidth: 1,
            goalLineColors: [ "#666633", "#999966", "#cc6666", "#663333" ],
            events: [],
            eventStrokeWidth: 1,
            eventLineColors: [ "#005a04", "#ccffbb", "#3a5f0b", "#005502" ],
            rangeSelect: null,
            rangeSelectColor: "#eef",
            resize: !1
        }, d.prototype.setData = function(a, c) {
            var d, e, f, g, h, i, j, k, l, m, n, o, p, q, r;
            return null == c && (c = !0), this.options.data = a, null == a || 0 === a.length ? (this.data = [], 
            this.raphael.clear(), null != this.hover && this.hover.hide(), void 0) : (o = this.cumulative ? 0 : null, 
            p = this.cumulative ? 0 : null, this.options.goals.length > 0 && (h = Math.min.apply(Math, this.options.goals), 
            g = Math.max.apply(Math, this.options.goals), p = null != p ? Math.min(p, h) : h, 
            o = null != o ? Math.max(o, g) : g), this.data = function() {
                var c, d, g;
                for (g = [], f = c = 0, d = a.length; d > c; f = ++c) j = a[f], i = {
                    src: j
                }, i.label = j[this.options.xkey], this.options.parseTime ? (i.x = b.parseDate(i.label), 
                this.options.dateFormat ? i.label = this.options.dateFormat(i.x) : "number" == typeof i.label && (i.label = new Date(i.label).toString())) : (i.x = f, 
                this.options.xLabelFormat && (i.label = this.options.xLabelFormat(i))), l = 0, i.y = function() {
                    var a, b, c, d;
                    for (c = this.options.ykeys, d = [], e = a = 0, b = c.length; b > a; e = ++a) n = c[e], 
                    q = j[n], "string" == typeof q && (q = parseFloat(q)), null != q && "number" != typeof q && (q = null), 
                    null != q && (this.cumulative ? l += q : null != o ? (o = Math.max(q, o), p = Math.min(q, p)) : o = p = q), 
                    this.cumulative && null != l && (o = Math.max(l, o), p = Math.min(l, p)), d.push(q);
                    return d;
                }.call(this), g.push(i);
                return g;
            }.call(this), this.options.parseTime && (this.data = this.data.sort(function(a, b) {
                return (a.x > b.x) - (b.x > a.x);
            })), this.xmin = this.data[0].x, this.xmax = this.data[this.data.length - 1].x, 
            this.events = [], this.options.events.length > 0 && (this.events = this.options.parseTime ? function() {
                var a, c, e, f;
                for (e = this.options.events, f = [], a = 0, c = e.length; c > a; a++) d = e[a], 
                f.push(b.parseDate(d));
                return f;
            }.call(this) : this.options.events, this.xmax = Math.max(this.xmax, Math.max.apply(Math, this.events)), 
            this.xmin = Math.min(this.xmin, Math.min.apply(Math, this.events))), this.xmin === this.xmax && (this.xmin -= 1, 
            this.xmax += 1), this.ymin = this.yboundary("min", p), this.ymax = this.yboundary("max", o), 
            this.ymin === this.ymax && (p && (this.ymin -= 1), this.ymax += 1), ((r = this.options.axes) === !0 || "both" === r || "y" === r || this.options.grid === !0) && (this.options.ymax === this.gridDefaults.ymax && this.options.ymin === this.gridDefaults.ymin ? (this.grid = this.autoGridLines(this.ymin, this.ymax, this.options.numLines), 
            this.ymin = Math.min(this.ymin, this.grid[0]), this.ymax = Math.max(this.ymax, this.grid[this.grid.length - 1])) : (k = (this.ymax - this.ymin) / (this.options.numLines - 1), 
            this.grid = function() {
                var a, b, c, d;
                for (d = [], m = a = b = this.ymin, c = this.ymax; k > 0 ? c >= a : a >= c; m = a += k) d.push(m);
                return d;
            }.call(this))), this.dirty = !0, c ? this.redraw() : void 0);
        }, d.prototype.yboundary = function(a, b) {
            var c, d;
            return c = this.options["y" + a], "string" == typeof c ? "auto" === c.slice(0, 4) ? c.length > 5 ? (d = parseInt(c.slice(5), 10), 
            null == b ? d : Math[a](b, d)) : null != b ? b : 0 : parseInt(c, 10) : c;
        }, d.prototype.autoGridLines = function(a, b, c) {
            var d, e, f, g, h, i, j, k, l;
            return h = b - a, l = Math.floor(Math.log(h) / Math.log(10)), j = Math.pow(10, l), 
            e = Math.floor(a / j) * j, d = Math.ceil(b / j) * j, i = (d - e) / (c - 1), 1 === j && i > 1 && Math.ceil(i) !== i && (i = Math.ceil(i), 
            d = e + i * (c - 1)), 0 > e && d > 0 && (e = Math.floor(a / i) * i, d = Math.ceil(b / i) * i), 
            1 > i ? (g = Math.floor(Math.log(i) / Math.log(10)), f = function() {
                var a, b;
                for (b = [], k = a = e; i > 0 ? d >= a : a >= d; k = a += i) b.push(parseFloat(k.toFixed(1 - g)));
                return b;
            }()) : f = function() {
                var a, b;
                for (b = [], k = a = e; i > 0 ? d >= a : a >= d; k = a += i) b.push(k);
                return b;
            }(), f;
        }, d.prototype._calc = function() {
            var a, b, c, d, e, f, g, h;
            return e = this.el.width(), c = this.el.height(), (this.elementWidth !== e || this.elementHeight !== c || this.dirty) && (this.elementWidth = e, 
            this.elementHeight = c, this.dirty = !1, this.left = this.options.padding, this.right = this.elementWidth - this.options.padding, 
            this.top = this.options.padding, this.bottom = this.elementHeight - this.options.padding, 
            ((g = this.options.axes) === !0 || "both" === g || "y" === g) && (f = function() {
                var a, c, d, e;
                for (d = this.grid, e = [], a = 0, c = d.length; c > a; a++) b = d[a], e.push(this.measureText(this.yAxisFormat(b)).width);
                return e;
            }.call(this), this.left += Math.max.apply(Math, f)), ((h = this.options.axes) === !0 || "both" === h || "x" === h) && (a = function() {
                var a, b, c;
                for (c = [], d = a = 0, b = this.data.length; b >= 0 ? b > a : a > b; d = b >= 0 ? ++a : --a) c.push(this.measureText(this.data[d].text, -this.options.xLabelAngle).height);
                return c;
            }.call(this), this.bottom -= Math.max.apply(Math, a)), this.width = Math.max(1, this.right - this.left), 
            this.height = Math.max(1, this.bottom - this.top), this.dx = this.width / (this.xmax - this.xmin), 
            this.dy = this.height / (this.ymax - this.ymin), this.calc) ? this.calc() : void 0;
        }, d.prototype.transY = function(a) {
            return this.bottom - (a - this.ymin) * this.dy;
        }, d.prototype.transX = function(a) {
            return 1 === this.data.length ? (this.left + this.right) / 2 : this.left + (a - this.xmin) * this.dx;
        }, d.prototype.redraw = function() {
            return this.raphael.clear(), this._calc(), this.drawGrid(), this.drawGoals(), this.drawEvents(), 
            this.draw ? this.draw() : void 0;
        }, d.prototype.measureText = function(a, b) {
            var c, d;
            return null == b && (b = 0), d = this.raphael.text(100, 100, a).attr("font-size", this.options.gridTextSize).attr("font-family", this.options.gridTextFamily).attr("font-weight", this.options.gridTextWeight).rotate(b), 
            c = d.getBBox(), d.remove(), c;
        }, d.prototype.yAxisFormat = function(a) {
            return this.yLabelFormat(a);
        }, d.prototype.yLabelFormat = function(a) {
            return "function" == typeof this.options.yLabelFormat ? this.options.yLabelFormat(a) : "" + this.options.preUnits + b.commas(a) + this.options.postUnits;
        }, d.prototype.drawGrid = function() {
            var a, b, c, d, e, f, g, h;
            if (this.options.grid !== !1 || (e = this.options.axes) === !0 || "both" === e || "y" === e) {
                for (f = this.grid, h = [], c = 0, d = f.length; d > c; c++) a = f[c], b = this.transY(a), 
                ((g = this.options.axes) === !0 || "both" === g || "y" === g) && this.drawYAxisLabel(this.left - this.options.padding / 2, b, this.yAxisFormat(a)), 
                this.options.grid ? h.push(this.drawGridLine("M" + this.left + "," + b + "H" + (this.left + this.width))) : h.push(void 0);
                return h;
            }
        }, d.prototype.drawGoals = function() {
            var a, b, c, d, e, f, g;
            for (f = this.options.goals, g = [], c = d = 0, e = f.length; e > d; c = ++d) b = f[c], 
            a = this.options.goalLineColors[c % this.options.goalLineColors.length], g.push(this.drawGoal(b, a));
            return g;
        }, d.prototype.drawEvents = function() {
            var a, b, c, d, e, f, g;
            for (f = this.events, g = [], c = d = 0, e = f.length; e > d; c = ++d) b = f[c], 
            a = this.options.eventLineColors[c % this.options.eventLineColors.length], g.push(this.drawEvent(b, a));
            return g;
        }, d.prototype.drawGoal = function(a, b) {
            return this.raphael.path("M" + this.left + "," + this.transY(a) + "H" + this.right).attr("stroke", b).attr("stroke-width", this.options.goalStrokeWidth);
        }, d.prototype.drawEvent = function(a, b) {
            return this.raphael.path("M" + this.transX(a) + "," + this.bottom + "V" + this.top).attr("stroke", b).attr("stroke-width", this.options.eventStrokeWidth);
        }, d.prototype.drawYAxisLabel = function(a, b, c) {
            return this.raphael.text(a, b, c).attr("font-size", this.options.gridTextSize).attr("font-family", this.options.gridTextFamily).attr("font-weight", this.options.gridTextWeight).attr("fill", this.options.gridTextColor).attr("text-anchor", "end");
        }, d.prototype.drawGridLine = function(a) {
            return this.raphael.path(a).attr("stroke", this.options.gridLineColor).attr("stroke-width", this.options.gridStrokeWidth);
        }, d.prototype.startRange = function(a) {
            return this.hover.hide(), this.selectFrom = a, this.selectionRect.attr({
                x: a,
                width: 0
            }).show();
        }, d.prototype.endRange = function(a) {
            var b, c;
            return this.selectFrom ? (c = Math.min(this.selectFrom, a), b = Math.max(this.selectFrom, a), 
            this.options.rangeSelect.call(this.el, {
                start: this.data[this.hitTest(c)].x,
                end: this.data[this.hitTest(b)].x
            }), this.selectFrom = null) : void 0;
        }, d.prototype.resizeHandler = function() {
            return this.timeoutId = null, this.raphael.setSize(this.el.width(), this.el.height()), 
            this.redraw();
        }, d;
    }(b.EventEmitter), b.parseDate = function(a) {
        var b, c, d, e, f, g, h, i, j, k, l;
        return "number" == typeof a ? a : (c = a.match(/^(\d+) Q(\d)$/), e = a.match(/^(\d+)-(\d+)$/), 
        f = a.match(/^(\d+)-(\d+)-(\d+)$/), h = a.match(/^(\d+) W(\d+)$/), i = a.match(/^(\d+)-(\d+)-(\d+)[ T](\d+):(\d+)(Z|([+-])(\d\d):?(\d\d))?$/), 
        j = a.match(/^(\d+)-(\d+)-(\d+)[ T](\d+):(\d+):(\d+(\.\d+)?)(Z|([+-])(\d\d):?(\d\d))?$/), 
        c ? new Date(parseInt(c[1], 10), 3 * parseInt(c[2], 10) - 1, 1).getTime() : e ? new Date(parseInt(e[1], 10), parseInt(e[2], 10) - 1, 1).getTime() : f ? new Date(parseInt(f[1], 10), parseInt(f[2], 10) - 1, parseInt(f[3], 10)).getTime() : h ? (k = new Date(parseInt(h[1], 10), 0, 1), 
        4 !== k.getDay() && k.setMonth(0, 1 + (4 - k.getDay() + 7) % 7), k.getTime() + 6048e5 * parseInt(h[2], 10)) : i ? i[6] ? (g = 0, 
        "Z" !== i[6] && (g = 60 * parseInt(i[8], 10) + parseInt(i[9], 10), "+" === i[7] && (g = 0 - g)), 
        Date.UTC(parseInt(i[1], 10), parseInt(i[2], 10) - 1, parseInt(i[3], 10), parseInt(i[4], 10), parseInt(i[5], 10) + g)) : new Date(parseInt(i[1], 10), parseInt(i[2], 10) - 1, parseInt(i[3], 10), parseInt(i[4], 10), parseInt(i[5], 10)).getTime() : j ? (l = parseFloat(j[6]), 
        b = Math.floor(l), d = Math.round(1e3 * (l - b)), j[8] ? (g = 0, "Z" !== j[8] && (g = 60 * parseInt(j[10], 10) + parseInt(j[11], 10), 
        "+" === j[9] && (g = 0 - g)), Date.UTC(parseInt(j[1], 10), parseInt(j[2], 10) - 1, parseInt(j[3], 10), parseInt(j[4], 10), parseInt(j[5], 10) + g, b, d)) : new Date(parseInt(j[1], 10), parseInt(j[2], 10) - 1, parseInt(j[3], 10), parseInt(j[4], 10), parseInt(j[5], 10), b, d).getTime()) : new Date(parseInt(a, 10), 0, 1).getTime());
    }, b.Hover = function() {
        function c(c) {
            null == c && (c = {}), this.options = a.extend({}, b.Hover.defaults, c), this.el = a("<div class='" + this.options["class"] + "'></div>"), 
            this.el.hide(), this.options.parent.append(this.el);
        }
        return c.defaults = {
            class: "morris-hover morris-default-style"
        }, c.prototype.update = function(a, b, c) {
            return a ? (this.html(a), this.show(), this.moveTo(b, c)) : this.hide();
        }, c.prototype.html = function(a) {
            return this.el.html(a);
        }, c.prototype.moveTo = function(a, b) {
            var c, d, e, f, g, h;
            return g = this.options.parent.innerWidth(), f = this.options.parent.innerHeight(), 
            d = this.el.outerWidth(), c = this.el.outerHeight(), e = Math.min(Math.max(0, a - d / 2), g - d), 
            null != b ? (h = b - c - 10, 0 > h && (h = b + 10, h + c > f && (h = f / 2 - c / 2))) : h = f / 2 - c / 2, 
            this.el.css({
                left: e + "px",
                top: parseInt(h) + "px"
            });
        }, c.prototype.show = function() {
            return this.el.show();
        }, c.prototype.hide = function() {
            return this.el.hide();
        }, c;
    }(), b.Line = function(a) {
        function c(a) {
            return this.hilight = f(this.hilight, this), this.onHoverOut = f(this.onHoverOut, this), 
            this.onHoverMove = f(this.onHoverMove, this), this.onGridClick = f(this.onGridClick, this), 
            this instanceof b.Line ? (c.__super__.constructor.call(this, a), void 0) : new b.Line(a);
        }
        return h(c, a), c.prototype.init = function() {
            return "always" !== this.options.hideHover ? (this.hover = new b.Hover({
                parent: this.el
            }), this.on("hovermove", this.onHoverMove), this.on("hoverout", this.onHoverOut), 
            this.on("gridclick", this.onGridClick)) : void 0;
        }, c.prototype.defaults = {
            lineWidth: 3,
            pointSize: 4,
            lineColors: [ "#0b62a4", "#7A92A3", "#4da74d", "#afd8f8", "#edc240", "#cb4b4b", "#9440ed" ],
            pointStrokeWidths: [ 1 ],
            pointStrokeColors: [ "#ffffff" ],
            pointFillColors: [],
            smooth: !0,
            xLabels: "auto",
            xLabelFormat: null,
            xLabelMargin: 24,
            hideHover: !1
        }, c.prototype.calc = function() {
            return this.calcPoints(), this.generatePaths();
        }, c.prototype.calcPoints = function() {
            var a, b, c, d, e, f;
            for (e = this.data, f = [], c = 0, d = e.length; d > c; c++) a = e[c], a._x = this.transX(a.x), 
            a._y = function() {
                var c, d, e, f;
                for (e = a.y, f = [], c = 0, d = e.length; d > c; c++) b = e[c], null != b ? f.push(this.transY(b)) : f.push(b);
                return f;
            }.call(this), f.push(a._ymax = Math.min.apply(Math, [ this.bottom ].concat(function() {
                var c, d, e, f;
                for (e = a._y, f = [], c = 0, d = e.length; d > c; c++) b = e[c], null != b && f.push(b);
                return f;
            }())));
            return f;
        }, c.prototype.hitTest = function(a) {
            var b, c, d, e, f;
            if (0 === this.data.length) return null;
            for (f = this.data.slice(1), b = d = 0, e = f.length; e > d && (c = f[b], !(a < (c._x + this.data[b]._x) / 2)); b = ++d) ;
            return b;
        }, c.prototype.onGridClick = function(a, b) {
            var c;
            return c = this.hitTest(a), this.fire("click", c, this.data[c].src, a, b);
        }, c.prototype.onHoverMove = function(a) {
            var b;
            return b = this.hitTest(a), this.displayHoverForRow(b);
        }, c.prototype.onHoverOut = function() {
            return this.options.hideHover !== !1 ? this.displayHoverForRow(null) : void 0;
        }, c.prototype.displayHoverForRow = function(a) {
            var b;
            return null != a ? ((b = this.hover).update.apply(b, this.hoverContentForRow(a)), 
            this.hilight(a)) : (this.hover.hide(), this.hilight());
        }, c.prototype.hoverContentForRow = function(a) {
            var b, c, d, e, f, g, h;
            for (d = this.data[a], b = "<div class='morris-hover-row-label'>" + d.label + "</div>", 
            h = d.y, c = f = 0, g = h.length; g > f; c = ++f) e = h[c], b += "<div class='morris-hover-point' style='color: " + this.colorFor(d, c, "label") + "'>\n  " + this.options.labels[c] + ":\n  " + this.yLabelFormat(e) + "\n</div>";
            return "function" == typeof this.options.hoverCallback && (b = this.options.hoverCallback(a, this.options, b, d.src)), 
            [ b, d._x, d._ymax ];
        }, c.prototype.generatePaths = function() {
            var a, c, d, e;
            return this.paths = function() {
                var f, g, h, j;
                for (j = [], c = f = 0, g = this.options.ykeys.length; g >= 0 ? g > f : f > g; c = g >= 0 ? ++f : --f) e = "boolean" == typeof this.options.smooth ? this.options.smooth : (h = this.options.ykeys[c], 
                i.call(this.options.smooth, h) >= 0), a = function() {
                    var a, b, e, f;
                    for (e = this.data, f = [], a = 0, b = e.length; b > a; a++) d = e[a], void 0 !== d._y[c] && f.push({
                        x: d._x,
                        y: d._y[c]
                    });
                    return f;
                }.call(this), a.length > 1 ? j.push(b.Line.createPath(a, e, this.bottom)) : j.push(null);
                return j;
            }.call(this);
        }, c.prototype.draw = function() {
            var a;
            return ((a = this.options.axes) === !0 || "both" === a || "x" === a) && this.drawXAxis(), 
            this.drawSeries(), this.options.hideHover === !1 ? this.displayHoverForRow(this.data.length - 1) : void 0;
        }, c.prototype.drawXAxis = function() {
            var a, c, d, e, f, g, h, i, j, k, l = this;
            for (h = this.bottom + this.options.padding / 2, f = null, e = null, a = function(a, b) {
                var c, d, g, i, j;
                return c = l.drawXAxisLabel(l.transX(b), h, a), j = c.getBBox(), c.transform("r" + -l.options.xLabelAngle), 
                d = c.getBBox(), c.transform("t0," + d.height / 2 + "..."), 0 !== l.options.xLabelAngle && (i = -.5 * j.width * Math.cos(l.options.xLabelAngle * Math.PI / 180), 
                c.transform("t" + i + ",0...")), d = c.getBBox(), (null == f || f >= d.x + d.width || null != e && e >= d.x) && d.x >= 0 && d.x + d.width < l.el.width() ? (0 !== l.options.xLabelAngle && (g = 1.25 * l.options.gridTextSize / Math.sin(l.options.xLabelAngle * Math.PI / 180), 
                e = d.x - g), f = d.x - l.options.xLabelMargin) : c.remove();
            }, d = this.options.parseTime ? 1 === this.data.length && "auto" === this.options.xLabels ? [ [ this.data[0].label, this.data[0].x ] ] : b.labelSeries(this.xmin, this.xmax, this.width, this.options.xLabels, this.options.xLabelFormat) : function() {
                var a, b, c, d;
                for (c = this.data, d = [], a = 0, b = c.length; b > a; a++) g = c[a], d.push([ g.label, g.x ]);
                return d;
            }.call(this), d.reverse(), k = [], i = 0, j = d.length; j > i; i++) c = d[i], k.push(a(c[0], c[1]));
            return k;
        }, c.prototype.drawSeries = function() {
            var a, b, c, d, e, f;
            for (this.seriesPoints = [], a = b = d = this.options.ykeys.length - 1; 0 >= d ? 0 >= b : b >= 0; a = 0 >= d ? ++b : --b) this._drawLineFor(a);
            for (f = [], a = c = e = this.options.ykeys.length - 1; 0 >= e ? 0 >= c : c >= 0; a = 0 >= e ? ++c : --c) f.push(this._drawPointFor(a));
            return f;
        }, c.prototype._drawPointFor = function(a) {
            var b, c, d, e, f, g;
            for (this.seriesPoints[a] = [], f = this.data, g = [], d = 0, e = f.length; e > d; d++) c = f[d], 
            b = null, null != c._y[a] && (b = this.drawLinePoint(c._x, c._y[a], this.colorFor(c, a, "point"), a)), 
            g.push(this.seriesPoints[a].push(b));
            return g;
        }, c.prototype._drawLineFor = function(a) {
            var b;
            return b = this.paths[a], null !== b ? this.drawLinePath(b, this.colorFor(null, a, "line"), a) : void 0;
        }, c.createPath = function(a, c, d) {
            var e, f, g, h, i, j, k, l, m, n, o, p, q, r;
            for (k = "", c && (g = b.Line.gradients(a)), l = {
                y: null
            }, h = q = 0, r = a.length; r > q; h = ++q) e = a[h], null != e.y && (null != l.y ? c ? (f = g[h], 
            j = g[h - 1], i = (e.x - l.x) / 4, m = l.x + i, o = Math.min(d, l.y + i * j), n = e.x - i, 
            p = Math.min(d, e.y - i * f), k += "C" + m + "," + o + "," + n + "," + p + "," + e.x + "," + e.y) : k += "L" + e.x + "," + e.y : c && null == g[h] || (k += "M" + e.x + "," + e.y)), 
            l = e;
            return k;
        }, c.gradients = function(a) {
            var b, c, d, e, f, g, h, i;
            for (c = function(a, b) {
                return (a.y - b.y) / (a.x - b.x);
            }, i = [], d = g = 0, h = a.length; h > g; d = ++g) b = a[d], null != b.y ? (e = a[d + 1] || {
                y: null
            }, f = a[d - 1] || {
                y: null
            }, null != f.y && null != e.y ? i.push(c(f, e)) : null != f.y ? i.push(c(f, b)) : null != e.y ? i.push(c(b, e)) : i.push(null)) : i.push(null);
            return i;
        }, c.prototype.hilight = function(a) {
            var b, c, d, e, f;
            if (null !== this.prevHilight && this.prevHilight !== a) for (b = c = 0, e = this.seriesPoints.length - 1; e >= 0 ? e >= c : c >= e; b = e >= 0 ? ++c : --c) this.seriesPoints[b][this.prevHilight] && this.seriesPoints[b][this.prevHilight].animate(this.pointShrinkSeries(b));
            if (null !== a && this.prevHilight !== a) for (b = d = 0, f = this.seriesPoints.length - 1; f >= 0 ? f >= d : d >= f; b = f >= 0 ? ++d : --d) this.seriesPoints[b][a] && this.seriesPoints[b][a].animate(this.pointGrowSeries(b));
            return this.prevHilight = a;
        }, c.prototype.colorFor = function(a, b, c) {
            return "function" == typeof this.options.lineColors ? this.options.lineColors.call(this, a, b, c) : "point" === c ? this.options.pointFillColors[b % this.options.pointFillColors.length] || this.options.lineColors[b % this.options.lineColors.length] : this.options.lineColors[b % this.options.lineColors.length];
        }, c.prototype.drawXAxisLabel = function(a, b, c) {
            return this.raphael.text(a, b, c).attr("font-size", this.options.gridTextSize).attr("font-family", this.options.gridTextFamily).attr("font-weight", this.options.gridTextWeight).attr("fill", this.options.gridTextColor);
        }, c.prototype.drawLinePath = function(a, b, c) {
            return this.raphael.path(a).attr("stroke", b).attr("stroke-width", this.lineWidthForSeries(c));
        }, c.prototype.drawLinePoint = function(a, b, c, d) {
            return this.raphael.circle(a, b, this.pointSizeForSeries(d)).attr("fill", c).attr("stroke-width", this.pointStrokeWidthForSeries(d)).attr("stroke", this.pointStrokeColorForSeries(d));
        }, c.prototype.pointStrokeWidthForSeries = function(a) {
            return this.options.pointStrokeWidths[a % this.options.pointStrokeWidths.length];
        }, c.prototype.pointStrokeColorForSeries = function(a) {
            return this.options.pointStrokeColors[a % this.options.pointStrokeColors.length];
        }, c.prototype.lineWidthForSeries = function(a) {
            return this.options.lineWidth instanceof Array ? this.options.lineWidth[a % this.options.lineWidth.length] : this.options.lineWidth;
        }, c.prototype.pointSizeForSeries = function(a) {
            return this.options.pointSize instanceof Array ? this.options.pointSize[a % this.options.pointSize.length] : this.options.pointSize;
        }, c.prototype.pointGrowSeries = function(a) {
            return Raphael.animation({
                r: this.pointSizeForSeries(a) + 3
            }, 25, "linear");
        }, c.prototype.pointShrinkSeries = function(a) {
            return Raphael.animation({
                r: this.pointSizeForSeries(a)
            }, 25, "linear");
        }, c;
    }(b.Grid), b.labelSeries = function(c, d, e, f, g) {
        var h, i, j, k, l, m, n, o, p, q, r;
        if (j = 200 * (d - c) / e, i = new Date(c), n = b.LABEL_SPECS[f], void 0 === n) for (r = b.AUTO_LABEL_ORDER, 
        p = 0, q = r.length; q > p; p++) if (k = r[p], m = b.LABEL_SPECS[k], j >= m.span) {
            n = m;
            break;
        }
        for (void 0 === n && (n = b.LABEL_SPECS.second), g && (n = a.extend({}, n, {
            fmt: g
        })), h = n.start(i), l = []; (o = h.getTime()) <= d; ) o >= c && l.push([ n.fmt(h), o ]), 
        n.incr(h);
        return l;
    }, c = function(a) {
        return {
            span: 60 * a * 1e3,
            start: function(a) {
                return new Date(a.getFullYear(), a.getMonth(), a.getDate(), a.getHours());
            },
            fmt: function(a) {
                return "" + b.pad2(a.getHours()) + ":" + b.pad2(a.getMinutes());
            },
            incr: function(b) {
                return b.setUTCMinutes(b.getUTCMinutes() + a);
            }
        };
    }, d = function(a) {
        return {
            span: 1e3 * a,
            start: function(a) {
                return new Date(a.getFullYear(), a.getMonth(), a.getDate(), a.getHours(), a.getMinutes());
            },
            fmt: function(a) {
                return "" + b.pad2(a.getHours()) + ":" + b.pad2(a.getMinutes()) + ":" + b.pad2(a.getSeconds());
            },
            incr: function(b) {
                return b.setUTCSeconds(b.getUTCSeconds() + a);
            }
        };
    }, b.LABEL_SPECS = {
        decade: {
            span: 1728e8,
            start: function(a) {
                return new Date(a.getFullYear() - a.getFullYear() % 10, 0, 1);
            },
            fmt: function(a) {
                return "" + a.getFullYear();
            },
            incr: function(a) {
                return a.setFullYear(a.getFullYear() + 10);
            }
        },
        year: {
            span: 1728e7,
            start: function(a) {
                return new Date(a.getFullYear(), 0, 1);
            },
            fmt: function(a) {
                return "" + a.getFullYear();
            },
            incr: function(a) {
                return a.setFullYear(a.getFullYear() + 1);
            }
        },
        month: {
            span: 24192e5,
            start: function(a) {
                return new Date(a.getFullYear(), a.getMonth(), 1);
            },
            fmt: function(a) {
                return "" + a.getFullYear() + "-" + b.pad2(a.getMonth() + 1);
            },
            incr: function(a) {
                return a.setMonth(a.getMonth() + 1);
            }
        },
        week: {
            span: 6048e5,
            start: function(a) {
                return new Date(a.getFullYear(), a.getMonth(), a.getDate());
            },
            fmt: function(a) {
                return "" + a.getFullYear() + "-" + b.pad2(a.getMonth() + 1) + "-" + b.pad2(a.getDate());
            },
            incr: function(a) {
                return a.setDate(a.getDate() + 7);
            }
        },
        day: {
            span: 864e5,
            start: function(a) {
                return new Date(a.getFullYear(), a.getMonth(), a.getDate());
            },
            fmt: function(a) {
                return "" + a.getFullYear() + "-" + b.pad2(a.getMonth() + 1) + "-" + b.pad2(a.getDate());
            },
            incr: function(a) {
                return a.setDate(a.getDate() + 1);
            }
        },
        hour: c(60),
        "30min": c(30),
        "15min": c(15),
        "10min": c(10),
        "5min": c(5),
        minute: c(1),
        "30sec": d(30),
        "15sec": d(15),
        "10sec": d(10),
        "5sec": d(5),
        second: d(1)
    }, b.AUTO_LABEL_ORDER = [ "decade", "year", "month", "week", "day", "hour", "30min", "15min", "10min", "5min", "minute", "30sec", "15sec", "10sec", "5sec", "second" ], 
    b.Area = function(c) {
        function d(c) {
            var f;
            return this instanceof b.Area ? (f = a.extend({}, e, c), this.cumulative = !f.behaveLikeLine, 
            "auto" === f.fillOpacity && (f.fillOpacity = f.behaveLikeLine ? .8 : 1), d.__super__.constructor.call(this, f), 
            void 0) : new b.Area(c);
        }
        var e;
        return h(d, c), e = {
            fillOpacity: "auto",
            behaveLikeLine: !1
        }, d.prototype.calcPoints = function() {
            var a, b, c, d, e, f, g;
            for (f = this.data, g = [], d = 0, e = f.length; e > d; d++) a = f[d], a._x = this.transX(a.x), 
            b = 0, a._y = function() {
                var d, e, f, g;
                for (f = a.y, g = [], d = 0, e = f.length; e > d; d++) c = f[d], this.options.behaveLikeLine ? g.push(this.transY(c)) : (b += c || 0, 
                g.push(this.transY(b)));
                return g;
            }.call(this), g.push(a._ymax = Math.max.apply(Math, a._y));
            return g;
        }, d.prototype.drawSeries = function() {
            var a, b, c, d, e, f, g, h;
            for (this.seriesPoints = [], b = this.options.behaveLikeLine ? function() {
                f = [];
                for (var a = 0, b = this.options.ykeys.length - 1; b >= 0 ? b >= a : a >= b; b >= 0 ? a++ : a--) f.push(a);
                return f;
            }.apply(this) : function() {
                g = [];
                for (var a = e = this.options.ykeys.length - 1; 0 >= e ? 0 >= a : a >= 0; 0 >= e ? a++ : a--) g.push(a);
                return g;
            }.apply(this), h = [], c = 0, d = b.length; d > c; c++) a = b[c], this._drawFillFor(a), 
            this._drawLineFor(a), h.push(this._drawPointFor(a));
            return h;
        }, d.prototype._drawFillFor = function(a) {
            var b;
            return b = this.paths[a], null !== b ? (b += "L" + this.transX(this.xmax) + "," + this.bottom + "L" + this.transX(this.xmin) + "," + this.bottom + "Z", 
            this.drawFilledPath(b, this.fillForSeries(a))) : void 0;
        }, d.prototype.fillForSeries = function(a) {
            var b;
            return b = Raphael.rgb2hsl(this.colorFor(this.data[a], a, "line")), Raphael.hsl(b.h, this.options.behaveLikeLine ? .9 * b.s : .75 * b.s, Math.min(.98, this.options.behaveLikeLine ? 1.2 * b.l : 1.25 * b.l));
        }, d.prototype.drawFilledPath = function(a, b) {
            return this.raphael.path(a).attr("fill", b).attr("fill-opacity", this.options.fillOpacity).attr("stroke", "none");
        }, d;
    }(b.Line), b.Bar = function(c) {
        function d(c) {
            return this.onHoverOut = f(this.onHoverOut, this), this.onHoverMove = f(this.onHoverMove, this), 
            this.onGridClick = f(this.onGridClick, this), this instanceof b.Bar ? (d.__super__.constructor.call(this, a.extend({}, c, {
                parseTime: !1
            })), void 0) : new b.Bar(c);
        }
        return h(d, c), d.prototype.init = function() {
            return this.cumulative = this.options.stacked, "always" !== this.options.hideHover ? (this.hover = new b.Hover({
                parent: this.el
            }), this.on("hovermove", this.onHoverMove), this.on("hoverout", this.onHoverOut), 
            this.on("gridclick", this.onGridClick)) : void 0;
        }, d.prototype.defaults = {
            barSizeRatio: .75,
            barGap: 3,
            barColors: [ "#0b62a4", "#7a92a3", "#4da74d", "#afd8f8", "#edc240", "#cb4b4b", "#9440ed" ],
            barOpacity: 1,
            barRadius: [ 0, 0, 0, 0 ],
            xLabelMargin: 50
        }, d.prototype.calc = function() {
            var a;
            return this.calcBars(), this.options.hideHover === !1 ? (a = this.hover).update.apply(a, this.hoverContentForRow(this.data.length - 1)) : void 0;
        }, d.prototype.calcBars = function() {
            var a, b, c, d, e, f, g;
            for (f = this.data, g = [], a = d = 0, e = f.length; e > d; a = ++d) b = f[a], b._x = this.left + this.width * (a + .5) / this.data.length, 
            g.push(b._y = function() {
                var a, d, e, f;
                for (e = b.y, f = [], a = 0, d = e.length; d > a; a++) c = e[a], null != c ? f.push(this.transY(c)) : f.push(null);
                return f;
            }.call(this));
            return g;
        }, d.prototype.draw = function() {
            var a;
            return ((a = this.options.axes) === !0 || "both" === a || "x" === a) && this.drawXAxis(), 
            this.drawSeries();
        }, d.prototype.drawXAxis = function() {
            var a, b, c, d, e, f, g, h, i, j, k, l, m;
            for (j = this.bottom + (this.options.xAxisLabelTopPadding || this.options.padding / 2), 
            g = null, f = null, m = [], a = k = 0, l = this.data.length; l >= 0 ? l > k : k > l; a = l >= 0 ? ++k : --k) h = this.data[this.data.length - 1 - a], 
            b = this.drawXAxisLabel(h._x, j, h.label), i = b.getBBox(), b.transform("r" + -this.options.xLabelAngle), 
            c = b.getBBox(), b.transform("t0," + c.height / 2 + "..."), 0 !== this.options.xLabelAngle && (e = -.5 * i.width * Math.cos(this.options.xLabelAngle * Math.PI / 180), 
            b.transform("t" + e + ",0...")), (null == g || g >= c.x + c.width || null != f && f >= c.x) && c.x >= 0 && c.x + c.width < this.el.width() ? (0 !== this.options.xLabelAngle && (d = 1.25 * this.options.gridTextSize / Math.sin(this.options.xLabelAngle * Math.PI / 180), 
            f = c.x - d), m.push(g = c.x - this.options.xLabelMargin)) : m.push(b.remove());
            return m;
        }, d.prototype.drawSeries = function() {
            var a, b, c, d, e, f, g, h, i, j, k, l, m, n, o;
            return c = this.width / this.options.data.length, h = this.options.stacked ? 1 : this.options.ykeys.length, 
            a = (c * this.options.barSizeRatio - this.options.barGap * (h - 1)) / h, this.options.barSize && (a = Math.min(a, this.options.barSize)), 
            l = c - a * h - this.options.barGap * (h - 1), g = l / 2, o = this.ymin <= 0 && this.ymax >= 0 ? this.transY(0) : null, 
            this.bars = function() {
                var h, l, p, q;
                for (p = this.data, q = [], d = h = 0, l = p.length; l > h; d = ++h) i = p[d], e = 0, 
                q.push(function() {
                    var h, l, p, q;
                    for (p = i._y, q = [], j = h = 0, l = p.length; l > h; j = ++h) n = p[j], null !== n ? (o ? (m = Math.min(n, o), 
                    b = Math.max(n, o)) : (m = n, b = this.bottom), f = this.left + d * c + g, this.options.stacked || (f += j * (a + this.options.barGap)), 
                    k = b - m, this.options.verticalGridCondition && this.options.verticalGridCondition(i.x) && this.drawBar(this.left + d * c, this.top, c, Math.abs(this.top - this.bottom), this.options.verticalGridColor, this.options.verticalGridOpacity, this.options.barRadius), 
                    this.options.stacked && (m -= e), this.drawBar(f, m, a, k, this.colorFor(i, j, "bar"), this.options.barOpacity, this.options.barRadius), 
                    q.push(e += k)) : q.push(null);
                    return q;
                }.call(this));
                return q;
            }.call(this);
        }, d.prototype.colorFor = function(a, b, c) {
            var d, e;
            return "function" == typeof this.options.barColors ? (d = {
                x: a.x,
                y: a.y[b],
                label: a.label
            }, e = {
                index: b,
                key: this.options.ykeys[b],
                label: this.options.labels[b]
            }, this.options.barColors.call(this, d, e, c)) : this.options.barColors[b % this.options.barColors.length];
        }, d.prototype.hitTest = function(a) {
            return 0 === this.data.length ? null : (a = Math.max(Math.min(a, this.right), this.left), 
            Math.min(this.data.length - 1, Math.floor((a - this.left) / (this.width / this.data.length))));
        }, d.prototype.onGridClick = function(a, b) {
            var c;
            return c = this.hitTest(a), this.fire("click", c, this.data[c].src, a, b);
        }, d.prototype.onHoverMove = function(a) {
            var b, c;
            return b = this.hitTest(a), (c = this.hover).update.apply(c, this.hoverContentForRow(b));
        }, d.prototype.onHoverOut = function() {
            return this.options.hideHover !== !1 ? this.hover.hide() : void 0;
        }, d.prototype.hoverContentForRow = function(a) {
            var b, c, d, e, f, g, h, i;
            for (d = this.data[a], b = "<div class='morris-hover-row-label'>" + d.label + "</div>", 
            i = d.y, c = g = 0, h = i.length; h > g; c = ++g) f = i[c], b += "<div class='morris-hover-point' style='color: " + this.colorFor(d, c, "label") + "'>\n  " + this.options.labels[c] + ":\n  " + this.yLabelFormat(f) + "\n</div>";
            return "function" == typeof this.options.hoverCallback && (b = this.options.hoverCallback(a, this.options, b, d.src)), 
            e = this.left + (a + .5) * this.width / this.data.length, [ b, e ];
        }, d.prototype.drawXAxisLabel = function(a, b, c) {
            var d;
            return d = this.raphael.text(a, b, c).attr("font-size", this.options.gridTextSize).attr("font-family", this.options.gridTextFamily).attr("font-weight", this.options.gridTextWeight).attr("fill", this.options.gridTextColor);
        }, d.prototype.drawBar = function(a, b, c, d, e, f, g) {
            var h, i;
            return h = Math.max.apply(Math, g), i = 0 === h || h > d ? this.raphael.rect(a, b, c, d) : this.raphael.path(this.roundedRect(a, b, c, d, g)), 
            i.attr("fill", e).attr("fill-opacity", f).attr("stroke", "none");
        }, d.prototype.roundedRect = function(a, b, c, d, e) {
            return null == e && (e = [ 0, 0, 0, 0 ]), [ "M", a, e[0] + b, "Q", a, b, a + e[0], b, "L", a + c - e[1], b, "Q", a + c, b, a + c, b + e[1], "L", a + c, b + d - e[2], "Q", a + c, b + d, a + c - e[2], b + d, "L", a + e[3], b + d, "Q", a, b + d, a, b + d - e[3], "Z" ];
        }, d;
    }(b.Grid), b.Donut = function(c) {
        function d(c) {
            this.resizeHandler = f(this.resizeHandler, this), this.select = f(this.select, this), 
            this.click = f(this.click, this);
            var d = this;
            if (!(this instanceof b.Donut)) return new b.Donut(c);
            if (this.options = a.extend({}, this.defaults, c), this.el = "string" == typeof c.element ? a(document.getElementById(c.element)) : a(c.element), 
            null === this.el || 0 === this.el.length) throw new Error("Graph placeholder not found.");
            void 0 !== c.data && 0 !== c.data.length && (this.raphael = new Raphael(this.el[0]), 
            this.options.resize && a(window).bind("resize", function() {
                return null != d.timeoutId && window.clearTimeout(d.timeoutId), d.timeoutId = window.setTimeout(d.resizeHandler, 100);
            }), this.setData(c.data));
        }
        return h(d, c), d.prototype.defaults = {
            colors: [ "#0B62A4", "#3980B5", "#679DC6", "#95BBD7", "#B0CCE1", "#095791", "#095085", "#083E67", "#052C48", "#042135" ],
            backgroundColor: "#FFFFFF",
            labelColor: "#000000",
            formatter: b.commas,
            resize: !1
        }, d.prototype.redraw = function() {
            var a, c, d, e, f, g, h, i, j, k, l, m, n, o, p, q, r, s, t, u, v, w, x;
            for (this.raphael.clear(), c = this.el.width() / 2, d = this.el.height() / 2, n = (Math.min(c, d) - 10) / 3, 
            l = 0, u = this.values, o = 0, r = u.length; r > o; o++) m = u[o], l += m;
            for (i = 5 / (2 * n), a = 1.9999 * Math.PI - i * this.data.length, g = 0, f = 0, 
            this.segments = [], v = this.values, e = p = 0, s = v.length; s > p; e = ++p) m = v[e], 
            j = g + i + a * (m / l), k = new b.DonutSegment(c, d, 2 * n, n, g, j, this.data[e].color || this.options.colors[f % this.options.colors.length], this.options.backgroundColor, f, this.raphael), 
            k.render(), this.segments.push(k), k.on("hover", this.select), k.on("click", this.click), 
            g = j, f += 1;
            for (this.text1 = this.drawEmptyDonutLabel(c, d - 10, this.options.labelColor, 15, 800), 
            this.text2 = this.drawEmptyDonutLabel(c, d + 10, this.options.labelColor, 14), h = Math.max.apply(Math, this.values), 
            f = 0, w = this.values, x = [], q = 0, t = w.length; t > q; q++) {
                if (m = w[q], m === h) {
                    this.select(f);
                    break;
                }
                x.push(f += 1);
            }
            return x;
        }, d.prototype.setData = function(a) {
            var b;
            return this.data = a, this.values = function() {
                var a, c, d, e;
                for (d = this.data, e = [], a = 0, c = d.length; c > a; a++) b = d[a], e.push(parseFloat(b.value));
                return e;
            }.call(this), this.redraw();
        }, d.prototype.click = function(a) {
            return this.fire("click", a, this.data[a]);
        }, d.prototype.select = function(a) {
            var b, c, d, e, f, g;
            for (g = this.segments, e = 0, f = g.length; f > e; e++) c = g[e], c.deselect();
            return d = this.segments[a], d.select(), b = this.data[a], this.setLabels(b.label, this.options.formatter(b.value, b));
        }, d.prototype.setLabels = function(a, b) {
            var c, d, e, f, g, h, i, j;
            return c = 2 * (Math.min(this.el.width() / 2, this.el.height() / 2) - 10) / 3, f = 1.8 * c, 
            e = c / 2, d = c / 3, this.text1.attr({
                text: a,
                transform: ""
            }), g = this.text1.getBBox(), h = Math.min(f / g.width, e / g.height), this.text1.attr({
                transform: "S" + h + "," + h + "," + (g.x + g.width / 2) + "," + (g.y + g.height)
            }), this.text2.attr({
                text: b,
                transform: ""
            }), i = this.text2.getBBox(), j = Math.min(f / i.width, d / i.height), this.text2.attr({
                transform: "S" + j + "," + j + "," + (i.x + i.width / 2) + "," + i.y
            });
        }, d.prototype.drawEmptyDonutLabel = function(a, b, c, d, e) {
            var f;
            return f = this.raphael.text(a, b, "").attr("font-size", d).attr("fill", c), null != e && f.attr("font-weight", e), 
            f;
        }, d.prototype.resizeHandler = function() {
            return this.timeoutId = null, this.raphael.setSize(this.el.width(), this.el.height()), 
            this.redraw();
        }, d;
    }(b.EventEmitter), b.DonutSegment = function(a) {
        function b(a, b, c, d, e, g, h, i, j, k) {
            this.cx = a, this.cy = b, this.inner = c, this.outer = d, this.color = h, this.backgroundColor = i, 
            this.index = j, this.raphael = k, this.deselect = f(this.deselect, this), this.select = f(this.select, this), 
            this.sin_p0 = Math.sin(e), this.cos_p0 = Math.cos(e), this.sin_p1 = Math.sin(g), 
            this.cos_p1 = Math.cos(g), this.is_long = g - e > Math.PI ? 1 : 0, this.path = this.calcSegment(this.inner + 3, this.inner + this.outer - 5), 
            this.selectedPath = this.calcSegment(this.inner + 3, this.inner + this.outer), this.hilight = this.calcArc(this.inner);
        }
        return h(b, a), b.prototype.calcArcPoints = function(a) {
            return [ this.cx + a * this.sin_p0, this.cy + a * this.cos_p0, this.cx + a * this.sin_p1, this.cy + a * this.cos_p1 ];
        }, b.prototype.calcSegment = function(a, b) {
            var c, d, e, f, g, h, i, j, k, l;
            return k = this.calcArcPoints(a), c = k[0], e = k[1], d = k[2], f = k[3], l = this.calcArcPoints(b), 
            g = l[0], i = l[1], h = l[2], j = l[3], "M" + c + "," + e + ("A" + a + "," + a + ",0," + this.is_long + ",0," + d + "," + f) + ("L" + h + "," + j) + ("A" + b + "," + b + ",0," + this.is_long + ",1," + g + "," + i) + "Z";
        }, b.prototype.calcArc = function(a) {
            var b, c, d, e, f;
            return f = this.calcArcPoints(a), b = f[0], d = f[1], c = f[2], e = f[3], "M" + b + "," + d + ("A" + a + "," + a + ",0," + this.is_long + ",0," + c + "," + e);
        }, b.prototype.render = function() {
            var a = this;
            return this.arc = this.drawDonutArc(this.hilight, this.color), this.seg = this.drawDonutSegment(this.path, this.color, this.backgroundColor, function() {
                return a.fire("hover", a.index);
            }, function() {
                return a.fire("click", a.index);
            });
        }, b.prototype.drawDonutArc = function(a, b) {
            return this.raphael.path(a).attr({
                stroke: b,
                "stroke-width": 2,
                opacity: 0
            });
        }, b.prototype.drawDonutSegment = function(a, b, c, d, e) {
            return this.raphael.path(a).attr({
                fill: b,
                stroke: c,
                "stroke-width": 3
            }).hover(d).click(e);
        }, b.prototype.select = function() {
            return this.selected ? void 0 : (this.seg.animate({
                path: this.selectedPath
            }, 150, "<>"), this.arc.animate({
                opacity: 1
            }, 150, "<>"), this.selected = !0);
        }, b.prototype.deselect = function() {
            return this.selected ? (this.seg.animate({
                path: this.path
            }, 150, "<>"), this.arc.animate({
                opacity: 0
            }, 150, "<>"), this.selected = !1) : void 0;
        }, b;
    }(b.EventEmitter);
}).call(this);

!function($) {
    var apiParams = {
        set: {
            colors: 1,
            values: 1,
            backgroundColor: 1,
            scaleColors: 1,
            normalizeFunction: 1,
            focus: 1
        },
        get: {
            selectedRegions: 1,
            selectedMarkers: 1,
            mapObject: 1,
            regionName: 1
        }
    };
    $.fn.vectorMap = function(options) {
        var map, methodName, map = this.children(".jvectormap-container").data("mapObject");
        if ("addMap" === options) jvm.Map.maps[arguments[1]] = arguments[2]; else {
            if (("set" === options || "get" === options) && apiParams[options][arguments[1]]) return methodName = arguments[1].charAt(0).toUpperCase() + arguments[1].substr(1), 
            map[options + methodName].apply(map, Array.prototype.slice.call(arguments, 2));
            options = options || {}, options.container = this, map = new jvm.Map(options);
        }
        return this;
    };
}(jQuery), function(factory) {
    "function" == typeof define && define.amd ? define([ "jquery" ], factory) : "object" == typeof exports ? module.exports = factory : factory(jQuery);
}(function($) {
    function handler(event) {
        var orgEvent = event || window.event, args = slice.call(arguments, 1), delta = 0, deltaX = 0, deltaY = 0, absDelta = 0;
        if (event = $.event.fix(orgEvent), event.type = "mousewheel", "detail" in orgEvent && (deltaY = -1 * orgEvent.detail), 
        "wheelDelta" in orgEvent && (deltaY = orgEvent.wheelDelta), "wheelDeltaY" in orgEvent && (deltaY = orgEvent.wheelDeltaY), 
        "wheelDeltaX" in orgEvent && (deltaX = -1 * orgEvent.wheelDeltaX), "axis" in orgEvent && orgEvent.axis === orgEvent.HORIZONTAL_AXIS && (deltaX = -1 * deltaY, 
        deltaY = 0), delta = 0 === deltaY ? deltaX : deltaY, "deltaY" in orgEvent && (deltaY = -1 * orgEvent.deltaY, 
        delta = deltaY), "deltaX" in orgEvent && (deltaX = orgEvent.deltaX, 0 === deltaY && (delta = -1 * deltaX)), 
        0 !== deltaY || 0 !== deltaX) {
            if (1 === orgEvent.deltaMode) {
                var lineHeight = $.data(this, "mousewheel-line-height");
                delta *= lineHeight, deltaY *= lineHeight, deltaX *= lineHeight;
            } else if (2 === orgEvent.deltaMode) {
                var pageHeight = $.data(this, "mousewheel-page-height");
                delta *= pageHeight, deltaY *= pageHeight, deltaX *= pageHeight;
            }
            return absDelta = Math.max(Math.abs(deltaY), Math.abs(deltaX)), (!lowestDelta || lowestDelta > absDelta) && (lowestDelta = absDelta, 
            shouldAdjustOldDeltas(orgEvent, absDelta) && (lowestDelta /= 40)), shouldAdjustOldDeltas(orgEvent, absDelta) && (delta /= 40, 
            deltaX /= 40, deltaY /= 40), delta = Math[delta >= 1 ? "floor" : "ceil"](delta / lowestDelta), 
            deltaX = Math[deltaX >= 1 ? "floor" : "ceil"](deltaX / lowestDelta), deltaY = Math[deltaY >= 1 ? "floor" : "ceil"](deltaY / lowestDelta), 
            event.deltaX = deltaX, event.deltaY = deltaY, event.deltaFactor = lowestDelta, event.deltaMode = 0, 
            args.unshift(event, delta, deltaX, deltaY), nullLowestDeltaTimeout && clearTimeout(nullLowestDeltaTimeout), 
            nullLowestDeltaTimeout = setTimeout(nullLowestDelta, 200), ($.event.dispatch || $.event.handle).apply(this, args);
        }
    }
    function nullLowestDelta() {
        lowestDelta = null;
    }
    function shouldAdjustOldDeltas(orgEvent, absDelta) {
        return special.settings.adjustOldDeltas && "mousewheel" === orgEvent.type && absDelta % 120 === 0;
    }
    var nullLowestDeltaTimeout, lowestDelta, toFix = [ "wheel", "mousewheel", "DOMMouseScroll", "MozMousePixelScroll" ], toBind = "onwheel" in document || document.documentMode >= 9 ? [ "wheel" ] : [ "mousewheel", "DomMouseScroll", "MozMousePixelScroll" ], slice = Array.prototype.slice;
    if ($.event.fixHooks) for (var i = toFix.length; i; ) $.event.fixHooks[toFix[--i]] = $.event.mouseHooks;
    var special = $.event.special.mousewheel = {
        version: "3.1.9",
        setup: function() {
            if (this.addEventListener) for (var i = toBind.length; i; ) this.addEventListener(toBind[--i], handler, !1); else this.onmousewheel = handler;
            $.data(this, "mousewheel-line-height", special.getLineHeight(this)), $.data(this, "mousewheel-page-height", special.getPageHeight(this));
        },
        teardown: function() {
            if (this.removeEventListener) for (var i = toBind.length; i; ) this.removeEventListener(toBind[--i], handler, !1); else this.onmousewheel = null;
        },
        getLineHeight: function(elem) {
            return parseInt($(elem)["offsetParent" in $.fn ? "offsetParent" : "parent"]().css("fontSize"), 10);
        },
        getPageHeight: function(elem) {
            return $(elem).height();
        },
        settings: {
            adjustOldDeltas: !0
        }
    };
    $.fn.extend({
        mousewheel: function(fn) {
            return fn ? this.bind("mousewheel", fn) : this.trigger("mousewheel");
        },
        unmousewheel: function(fn) {
            return this.unbind("mousewheel", fn);
        }
    });
});

var jvm = {
    inherits: function(child, parent) {
        function temp() {}
        temp.prototype = parent.prototype, child.prototype = new temp(), child.prototype.constructor = child, 
        child.parentClass = parent;
    },
    mixin: function(target, source) {
        var prop;
        for (prop in source.prototype) source.prototype.hasOwnProperty(prop) && (target.prototype[prop] = source.prototype[prop]);
    },
    min: function(values) {
        var i, min = Number.MAX_VALUE;
        if (values instanceof Array) for (i = 0; i < values.length; i++) values[i] < min && (min = values[i]); else for (i in values) values[i] < min && (min = values[i]);
        return min;
    },
    max: function(values) {
        var i, max = Number.MIN_VALUE;
        if (values instanceof Array) for (i = 0; i < values.length; i++) values[i] > max && (max = values[i]); else for (i in values) values[i] > max && (max = values[i]);
        return max;
    },
    keys: function(object) {
        var key, keys = [];
        for (key in object) keys.push(key);
        return keys;
    },
    values: function(object) {
        var key, i, values = [];
        for (i = 0; i < arguments.length; i++) {
            object = arguments[i];
            for (key in object) values.push(object[key]);
        }
        return values;
    },
    whenImageLoaded: function(url) {
        var deferred = new jvm.$.Deferred(), img = jvm.$("<img/>");
        return img.error(function() {
            deferred.reject();
        }).load(function() {
            deferred.resolve(img);
        }), img.attr("src", url), deferred;
    },
    isImageUrl: function(s) {
        return /\.\w{3,4}$/.test(s);
    }
};

jvm.$ = jQuery, Array.prototype.indexOf || (Array.prototype.indexOf = function(searchElement, fromIndex) {
    var k;
    if (null == this) throw new TypeError('"this" is null or not defined');
    var O = Object(this), len = O.length >>> 0;
    if (0 === len) return -1;
    var n = +fromIndex || 0;
    if (1 / 0 === Math.abs(n) && (n = 0), n >= len) return -1;
    for (k = Math.max(n >= 0 ? n : len - Math.abs(n), 0); len > k; ) {
        if (k in O && O[k] === searchElement) return k;
        k++;
    }
    return -1;
}), jvm.AbstractElement = function(name, config) {
    this.node = this.createElement(name), this.name = name, this.properties = {}, config && this.set(config);
}, jvm.AbstractElement.prototype.set = function(property, value) {
    var key;
    if ("object" == typeof property) for (key in property) this.properties[key] = property[key], 
    this.applyAttr(key, property[key]); else this.properties[property] = value, this.applyAttr(property, value);
}, jvm.AbstractElement.prototype.get = function(property) {
    return this.properties[property];
}, jvm.AbstractElement.prototype.applyAttr = function(property, value) {
    this.node.setAttribute(property, value);
}, jvm.AbstractElement.prototype.remove = function() {
    jvm.$(this.node).remove();
}, jvm.AbstractCanvasElement = function(container, width, height) {
    this.container = container, this.setSize(width, height), this.rootElement = new jvm[this.classPrefix + "GroupElement"](), 
    this.node.appendChild(this.rootElement.node), this.container.appendChild(this.node);
}, jvm.AbstractCanvasElement.prototype.add = function(element, group) {
    group = group || this.rootElement, group.add(element), element.canvas = this;
}, jvm.AbstractCanvasElement.prototype.addPath = function(config, style, group) {
    var el = new jvm[this.classPrefix + "PathElement"](config, style);
    return this.add(el, group), el;
}, jvm.AbstractCanvasElement.prototype.addCircle = function(config, style, group) {
    var el = new jvm[this.classPrefix + "CircleElement"](config, style);
    return this.add(el, group), el;
}, jvm.AbstractCanvasElement.prototype.addImage = function(config, style, group) {
    var el = new jvm[this.classPrefix + "ImageElement"](config, style);
    return this.add(el, group), el;
}, jvm.AbstractCanvasElement.prototype.addText = function(config, style, group) {
    var el = new jvm[this.classPrefix + "TextElement"](config, style);
    return this.add(el, group), el;
}, jvm.AbstractCanvasElement.prototype.addGroup = function(parentGroup) {
    var el = new jvm[this.classPrefix + "GroupElement"]();
    return parentGroup ? parentGroup.node.appendChild(el.node) : this.node.appendChild(el.node), 
    el.canvas = this, el;
}, jvm.AbstractShapeElement = function(name, config, style) {
    this.style = style || {}, this.style.current = this.style.current || {}, this.isHovered = !1, 
    this.isSelected = !1, this.updateStyle();
}, jvm.AbstractShapeElement.prototype.setStyle = function(property, value) {
    var styles = {};
    "object" == typeof property ? styles = property : styles[property] = value, jvm.$.extend(this.style.current, styles), 
    this.updateStyle();
}, jvm.AbstractShapeElement.prototype.updateStyle = function() {
    var attrs = {};
    jvm.AbstractShapeElement.mergeStyles(attrs, this.style.initial), jvm.AbstractShapeElement.mergeStyles(attrs, this.style.current), 
    this.isHovered && jvm.AbstractShapeElement.mergeStyles(attrs, this.style.hover), 
    this.isSelected && (jvm.AbstractShapeElement.mergeStyles(attrs, this.style.selected), 
    this.isHovered && jvm.AbstractShapeElement.mergeStyles(attrs, this.style.selectedHover)), 
    this.set(attrs);
}, jvm.AbstractShapeElement.mergeStyles = function(styles, newStyles) {
    var key;
    newStyles = newStyles || {};
    for (key in newStyles) null === newStyles[key] ? delete styles[key] : styles[key] = newStyles[key];
}, jvm.SVGElement = function() {
    jvm.SVGElement.parentClass.apply(this, arguments);
}, jvm.inherits(jvm.SVGElement, jvm.AbstractElement), jvm.SVGElement.svgns = "http://www.w3.org/2000/svg", 
jvm.SVGElement.prototype.createElement = function(tagName) {
    return document.createElementNS(jvm.SVGElement.svgns, tagName);
}, jvm.SVGElement.prototype.addClass = function(className) {
    this.node.setAttribute("class", className);
}, jvm.SVGElement.prototype.getElementCtr = function(ctr) {
    return jvm["SVG" + ctr];
}, jvm.SVGElement.prototype.getBBox = function() {
    return this.node.getBBox();
}, jvm.SVGGroupElement = function() {
    jvm.SVGGroupElement.parentClass.call(this, "g");
}, jvm.inherits(jvm.SVGGroupElement, jvm.SVGElement), jvm.SVGGroupElement.prototype.add = function(element) {
    this.node.appendChild(element.node);
}, jvm.SVGCanvasElement = function() {
    this.classPrefix = "SVG", jvm.SVGCanvasElement.parentClass.call(this, "svg"), this.defsElement = new jvm.SVGElement("defs"), 
    this.node.appendChild(this.defsElement.node), jvm.AbstractCanvasElement.apply(this, arguments);
}, jvm.inherits(jvm.SVGCanvasElement, jvm.SVGElement), jvm.mixin(jvm.SVGCanvasElement, jvm.AbstractCanvasElement), 
jvm.SVGCanvasElement.prototype.setSize = function(width, height) {
    this.width = width, this.height = height, this.node.setAttribute("width", width), 
    this.node.setAttribute("height", height);
}, jvm.SVGCanvasElement.prototype.applyTransformParams = function(scale, transX, transY) {
    this.scale = scale, this.transX = transX, this.transY = transY, this.rootElement.node.setAttribute("transform", "scale(" + scale + ") translate(" + transX + ", " + transY + ")");
}, jvm.SVGShapeElement = function(name, config) {
    jvm.SVGShapeElement.parentClass.call(this, name, config), jvm.AbstractShapeElement.apply(this, arguments);
}, jvm.inherits(jvm.SVGShapeElement, jvm.SVGElement), jvm.mixin(jvm.SVGShapeElement, jvm.AbstractShapeElement), 
jvm.SVGShapeElement.prototype.applyAttr = function(attr, value) {
    var patternEl, imageEl, that = this;
    "fill" === attr && jvm.isImageUrl(value) ? jvm.SVGShapeElement.images[value] ? this.applyAttr("fill", "url(#image" + jvm.SVGShapeElement.images[value] + ")") : jvm.whenImageLoaded(value).then(function(img) {
        imageEl = new jvm.SVGElement("image"), imageEl.node.setAttributeNS("http://www.w3.org/1999/xlink", "href", value), 
        imageEl.applyAttr("x", "0"), imageEl.applyAttr("y", "0"), imageEl.applyAttr("width", img[0].width), 
        imageEl.applyAttr("height", img[0].height), patternEl = new jvm.SVGElement("pattern"), 
        patternEl.applyAttr("id", "image" + jvm.SVGShapeElement.imageCounter), patternEl.applyAttr("x", 0), 
        patternEl.applyAttr("y", 0), patternEl.applyAttr("width", img[0].width / 2), patternEl.applyAttr("height", img[0].height / 2), 
        patternEl.applyAttr("viewBox", "0 0 " + img[0].width + " " + img[0].height), patternEl.applyAttr("patternUnits", "userSpaceOnUse"), 
        patternEl.node.appendChild(imageEl.node), that.canvas.defsElement.node.appendChild(patternEl.node), 
        jvm.SVGShapeElement.images[value] = jvm.SVGShapeElement.imageCounter++, that.applyAttr("fill", "url(#image" + jvm.SVGShapeElement.images[value] + ")");
    }) : jvm.SVGShapeElement.parentClass.prototype.applyAttr.apply(this, arguments);
}, jvm.SVGShapeElement.imageCounter = 1, jvm.SVGShapeElement.images = {}, jvm.SVGPathElement = function(config, style) {
    jvm.SVGPathElement.parentClass.call(this, "path", config, style), this.node.setAttribute("fill-rule", "evenodd");
}, jvm.inherits(jvm.SVGPathElement, jvm.SVGShapeElement), jvm.SVGCircleElement = function(config, style) {
    jvm.SVGCircleElement.parentClass.call(this, "circle", config, style);
}, jvm.inherits(jvm.SVGCircleElement, jvm.SVGShapeElement), jvm.SVGImageElement = function(config, style) {
    jvm.SVGImageElement.parentClass.call(this, "image", config, style);
}, jvm.inherits(jvm.SVGImageElement, jvm.SVGShapeElement), jvm.SVGImageElement.prototype.applyAttr = function(attr, value) {
    var that = this;
    "image" == attr ? jvm.SVGImageElement.images[value] || jvm.whenImageLoaded(value).then(function(img) {
        that.node.setAttributeNS("http://www.w3.org/1999/xlink", "href", value), that.width = img[0].width, 
        that.height = img[0].height, that.applyAttr("width", that.width), that.applyAttr("height", that.height), 
        jvm.SVGImageElement.images[value] = jvm.SVGImageElement.imageCounter++, that.applyAttr("x", that.cx - that.width / 2), 
        that.applyAttr("y", that.cy - that.height / 2), jvm.$(that.node).trigger("imageloaded", [ img ]);
    }) : "cx" == attr ? (this.cx = value, this.width && this.applyAttr("x", value - this.width / 2)) : "cy" == attr ? (this.cy = value, 
    this.height && this.applyAttr("y", value - this.height / 2)) : jvm.SVGImageElement.parentClass.prototype.applyAttr.apply(this, arguments);
}, jvm.SVGImageElement.imageCounter = 1, jvm.SVGImageElement.images = {}, jvm.SVGTextElement = function(config, style) {
    jvm.SVGTextElement.parentClass.call(this, "text", config, style);
}, jvm.inherits(jvm.SVGTextElement, jvm.SVGShapeElement), jvm.SVGTextElement.prototype.applyAttr = function(attr, value) {
    "text" === attr ? this.node.textContent = value : jvm.SVGTextElement.parentClass.prototype.applyAttr.apply(this, arguments);
}, jvm.VMLElement = function() {
    jvm.VMLElement.VMLInitialized || jvm.VMLElement.initializeVML(), jvm.VMLElement.parentClass.apply(this, arguments);
}, jvm.inherits(jvm.VMLElement, jvm.AbstractElement), jvm.VMLElement.VMLInitialized = !1, 
jvm.VMLElement.initializeVML = function() {
    try {
        document.namespaces.rvml || document.namespaces.add("rvml", "urn:schemas-microsoft-com:vml"), 
        jvm.VMLElement.prototype.createElement = function(tagName) {
            return document.createElement("<rvml:" + tagName + ' class="rvml">');
        };
    } catch (e) {
        jvm.VMLElement.prototype.createElement = function(tagName) {
            return document.createElement("<" + tagName + ' xmlns="urn:schemas-microsoft.com:vml" class="rvml">');
        };
    }
    document.createStyleSheet().addRule(".rvml", "behavior:url(#default#VML)"), jvm.VMLElement.VMLInitialized = !0;
}, jvm.VMLElement.prototype.getElementCtr = function(ctr) {
    return jvm["VML" + ctr];
}, jvm.VMLElement.prototype.addClass = function(className) {
    jvm.$(this.node).addClass(className);
}, jvm.VMLElement.prototype.applyAttr = function(attr, value) {
    this.node[attr] = value;
}, jvm.VMLElement.prototype.getBBox = function() {
    var node = jvm.$(this.node);
    return {
        x: node.position().left / this.canvas.scale,
        y: node.position().top / this.canvas.scale,
        width: node.width() / this.canvas.scale,
        height: node.height() / this.canvas.scale
    };
}, jvm.VMLGroupElement = function() {
    jvm.VMLGroupElement.parentClass.call(this, "group"), this.node.style.left = "0px", 
    this.node.style.top = "0px", this.node.coordorigin = "0 0";
}, jvm.inherits(jvm.VMLGroupElement, jvm.VMLElement), jvm.VMLGroupElement.prototype.add = function(element) {
    this.node.appendChild(element.node);
}, jvm.VMLCanvasElement = function() {
    this.classPrefix = "VML", jvm.VMLCanvasElement.parentClass.call(this, "group"), 
    jvm.AbstractCanvasElement.apply(this, arguments), this.node.style.position = "absolute";
}, jvm.inherits(jvm.VMLCanvasElement, jvm.VMLElement), jvm.mixin(jvm.VMLCanvasElement, jvm.AbstractCanvasElement), 
jvm.VMLCanvasElement.prototype.setSize = function(width, height) {
    var paths, groups, i, l;
    if (this.width = width, this.height = height, this.node.style.width = width + "px", 
    this.node.style.height = height + "px", this.node.coordsize = width + " " + height, 
    this.node.coordorigin = "0 0", this.rootElement) {
        for (paths = this.rootElement.node.getElementsByTagName("shape"), i = 0, l = paths.length; l > i; i++) paths[i].coordsize = width + " " + height, 
        paths[i].style.width = width + "px", paths[i].style.height = height + "px";
        for (groups = this.node.getElementsByTagName("group"), i = 0, l = groups.length; l > i; i++) groups[i].coordsize = width + " " + height, 
        groups[i].style.width = width + "px", groups[i].style.height = height + "px";
    }
}, jvm.VMLCanvasElement.prototype.applyTransformParams = function(scale, transX, transY) {
    this.scale = scale, this.transX = transX, this.transY = transY, this.rootElement.node.coordorigin = this.width - transX - this.width / 100 + "," + (this.height - transY - this.height / 100), 
    this.rootElement.node.coordsize = this.width / scale + "," + this.height / scale;
}, jvm.VMLShapeElement = function(name, config) {
    jvm.VMLShapeElement.parentClass.call(this, name, config), this.fillElement = new jvm.VMLElement("fill"), 
    this.strokeElement = new jvm.VMLElement("stroke"), this.node.appendChild(this.fillElement.node), 
    this.node.appendChild(this.strokeElement.node), this.node.stroked = !1, jvm.AbstractShapeElement.apply(this, arguments);
}, jvm.inherits(jvm.VMLShapeElement, jvm.VMLElement), jvm.mixin(jvm.VMLShapeElement, jvm.AbstractShapeElement), 
jvm.VMLShapeElement.prototype.applyAttr = function(attr, value) {
    switch (attr) {
      case "fill":
        this.node.fillcolor = value;
        break;

      case "fill-opacity":
        this.fillElement.node.opacity = Math.round(100 * value) + "%";
        break;

      case "stroke":
        this.node.stroked = "none" === value ? !1 : !0, this.node.strokecolor = value;
        break;

      case "stroke-opacity":
        this.strokeElement.node.opacity = Math.round(100 * value) + "%";
        break;

      case "stroke-width":
        this.node.stroked = 0 === parseInt(value, 10) ? !1 : !0, this.node.strokeweight = value;
        break;

      case "d":
        this.node.path = jvm.VMLPathElement.pathSvgToVml(value);
        break;

      default:
        jvm.VMLShapeElement.parentClass.prototype.applyAttr.apply(this, arguments);
    }
}, jvm.VMLPathElement = function(config, style) {
    var scale = new jvm.VMLElement("skew");
    jvm.VMLPathElement.parentClass.call(this, "shape", config, style), this.node.coordorigin = "0 0", 
    scale.node.on = !0, scale.node.matrix = "0.01,0,0,0.01,0,0", scale.node.offset = "0,0", 
    this.node.appendChild(scale.node);
}, jvm.inherits(jvm.VMLPathElement, jvm.VMLShapeElement), jvm.VMLPathElement.prototype.applyAttr = function(attr, value) {
    "d" === attr ? this.node.path = jvm.VMLPathElement.pathSvgToVml(value) : jvm.VMLShapeElement.prototype.applyAttr.call(this, attr, value);
}, jvm.VMLPathElement.pathSvgToVml = function(path) {
    var ctrlx, ctrly, cx = 0, cy = 0;
    return path = path.replace(/(-?\d+)e(-?\d+)/g, "0"), path.replace(/([MmLlHhVvCcSs])\s*((?:-?\d*(?:\.\d+)?\s*,?\s*)+)/g, function(segment, letter, coords) {
        coords = coords.replace(/(\d)-/g, "$1,-").replace(/^\s+/g, "").replace(/\s+$/g, "").replace(/\s+/g, ",").split(","), 
        coords[0] || coords.shift();
        for (var i = 0, l = coords.length; l > i; i++) coords[i] = Math.round(100 * coords[i]);
        switch (letter) {
          case "m":
            return cx += coords[0], cy += coords[1], "t" + coords.join(",");

          case "M":
            return cx = coords[0], cy = coords[1], "m" + coords.join(",");

          case "l":
            return cx += coords[0], cy += coords[1], "r" + coords.join(",");

          case "L":
            return cx = coords[0], cy = coords[1], "l" + coords.join(",");

          case "h":
            return cx += coords[0], "r" + coords[0] + ",0";

          case "H":
            return cx = coords[0], "l" + cx + "," + cy;

          case "v":
            return cy += coords[0], "r0," + coords[0];

          case "V":
            return cy = coords[0], "l" + cx + "," + cy;

          case "c":
            return ctrlx = cx + coords[coords.length - 4], ctrly = cy + coords[coords.length - 3], 
            cx += coords[coords.length - 2], cy += coords[coords.length - 1], "v" + coords.join(",");

          case "C":
            return ctrlx = coords[coords.length - 4], ctrly = coords[coords.length - 3], cx = coords[coords.length - 2], 
            cy = coords[coords.length - 1], "c" + coords.join(",");

          case "s":
            return coords.unshift(cy - ctrly), coords.unshift(cx - ctrlx), ctrlx = cx + coords[coords.length - 4], 
            ctrly = cy + coords[coords.length - 3], cx += coords[coords.length - 2], cy += coords[coords.length - 1], 
            "v" + coords.join(",");

          case "S":
            return coords.unshift(cy + cy - ctrly), coords.unshift(cx + cx - ctrlx), ctrlx = coords[coords.length - 4], 
            ctrly = coords[coords.length - 3], cx = coords[coords.length - 2], cy = coords[coords.length - 1], 
            "c" + coords.join(",");
        }
        return "";
    }).replace(/z/g, "e");
}, jvm.VMLCircleElement = function(config, style) {
    jvm.VMLCircleElement.parentClass.call(this, "oval", config, style);
}, jvm.inherits(jvm.VMLCircleElement, jvm.VMLShapeElement), jvm.VMLCircleElement.prototype.applyAttr = function(attr, value) {
    switch (attr) {
      case "r":
        this.node.style.width = 2 * value + "px", this.node.style.height = 2 * value + "px", 
        this.applyAttr("cx", this.get("cx") || 0), this.applyAttr("cy", this.get("cy") || 0);
        break;

      case "cx":
        if (!value) return;
        this.node.style.left = value - (this.get("r") || 0) + "px";
        break;

      case "cy":
        if (!value) return;
        this.node.style.top = value - (this.get("r") || 0) + "px";
        break;

      default:
        jvm.VMLCircleElement.parentClass.prototype.applyAttr.call(this, attr, value);
    }
}, jvm.VectorCanvas = function(container, width, height) {
    return this.mode = window.SVGAngle ? "svg" : "vml", this.impl = "svg" == this.mode ? new jvm.SVGCanvasElement(container, width, height) : new jvm.VMLCanvasElement(container, width, height), 
    this.impl.mode = this.mode, this.impl;
}, jvm.SimpleScale = function(scale) {
    this.scale = scale;
}, jvm.SimpleScale.prototype.getValue = function(value) {
    return value;
}, jvm.OrdinalScale = function(scale) {
    this.scale = scale;
}, jvm.OrdinalScale.prototype.getValue = function(value) {
    return this.scale[value];
}, jvm.OrdinalScale.prototype.getTicks = function() {
    var key, ticks = [];
    for (key in this.scale) ticks.push({
        label: key,
        value: this.scale[key]
    });
    return ticks;
}, jvm.NumericScale = function(scale, normalizeFunction, minValue, maxValue) {
    this.scale = [], normalizeFunction = normalizeFunction || "linear", scale && this.setScale(scale), 
    normalizeFunction && this.setNormalizeFunction(normalizeFunction), "undefined" != typeof minValue && this.setMin(minValue), 
    "undefined" != typeof maxValue && this.setMin(maxValue);
}, jvm.NumericScale.prototype = {
    setMin: function(min) {
        this.clearMinValue = min, this.minValue = "function" == typeof this.normalize ? this.normalize(min) : min;
    },
    setMax: function(max) {
        this.clearMaxValue = max, this.maxValue = "function" == typeof this.normalize ? this.normalize(max) : max;
    },
    setScale: function(scale) {
        var i;
        for (this.scale = [], i = 0; i < scale.length; i++) this.scale[i] = [ scale[i] ];
    },
    setNormalizeFunction: function(f) {
        "polynomial" === f ? this.normalize = function(value) {
            return Math.pow(value, .2);
        } : "linear" === f ? delete this.normalize : this.normalize = f, this.setMin(this.clearMinValue), 
        this.setMax(this.clearMaxValue);
    },
    getValue: function(value) {
        var l, c, lengthes = [], fullLength = 0, i = 0;
        for ("function" == typeof this.normalize && (value = this.normalize(value)), i = 0; i < this.scale.length - 1; i++) l = this.vectorLength(this.vectorSubtract(this.scale[i + 1], this.scale[i])), 
        lengthes.push(l), fullLength += l;
        for (c = (this.maxValue - this.minValue) / fullLength, i = 0; i < lengthes.length; i++) lengthes[i] *= c;
        for (i = 0, value -= this.minValue; value - lengthes[i] >= 0; ) value -= lengthes[i], 
        i++;
        return value = this.vectorToNum(i == this.scale.length - 1 ? this.scale[i] : this.vectorAdd(this.scale[i], this.vectorMult(this.vectorSubtract(this.scale[i + 1], this.scale[i]), value / lengthes[i])));
    },
    vectorToNum: function(vector) {
        var i, num = 0;
        for (i = 0; i < vector.length; i++) num += Math.round(vector[i]) * Math.pow(256, vector.length - i - 1);
        return num;
    },
    vectorSubtract: function(vector1, vector2) {
        var i, vector = [];
        for (i = 0; i < vector1.length; i++) vector[i] = vector1[i] - vector2[i];
        return vector;
    },
    vectorAdd: function(vector1, vector2) {
        var i, vector = [];
        for (i = 0; i < vector1.length; i++) vector[i] = vector1[i] + vector2[i];
        return vector;
    },
    vectorMult: function(vector, num) {
        var i, result = [];
        for (i = 0; i < vector.length; i++) result[i] = vector[i] * num;
        return result;
    },
    vectorLength: function(vector) {
        var i, result = 0;
        for (i = 0; i < vector.length; i++) result += vector[i] * vector[i];
        return Math.sqrt(result);
    },
    getTicks: function() {
        var tick, v, m = 5, extent = [ this.clearMinValue, this.clearMaxValue ], span = extent[1] - extent[0], step = Math.pow(10, Math.floor(Math.log(span / m) / Math.LN10)), err = m / span * step, ticks = [];
        for (.15 >= err ? step *= 10 : .35 >= err ? step *= 5 : .75 >= err && (step *= 2), 
        extent[0] = Math.floor(extent[0] / step) * step, extent[1] = Math.ceil(extent[1] / step) * step, 
        tick = extent[0]; tick <= extent[1]; ) v = tick == extent[0] ? this.clearMinValue : tick == extent[1] ? this.clearMaxValue : tick, 
        ticks.push({
            label: tick,
            value: this.getValue(v)
        }), tick += step;
        return ticks;
    }
}, jvm.ColorScale = function() {
    jvm.ColorScale.parentClass.apply(this, arguments);
}, jvm.inherits(jvm.ColorScale, jvm.NumericScale), jvm.ColorScale.prototype.setScale = function(scale) {
    var i;
    for (i = 0; i < scale.length; i++) this.scale[i] = jvm.ColorScale.rgbToArray(scale[i]);
}, jvm.ColorScale.prototype.getValue = function(value) {
    return jvm.ColorScale.numToRgb(jvm.ColorScale.parentClass.prototype.getValue.call(this, value));
}, jvm.ColorScale.arrayToRgb = function(ar) {
    var d, i, rgb = "#";
    for (i = 0; i < ar.length; i++) d = ar[i].toString(16), rgb += 1 == d.length ? "0" + d : d;
    return rgb;
}, jvm.ColorScale.numToRgb = function(num) {
    for (num = num.toString(16); num.length < 6; ) num = "0" + num;
    return "#" + num;
}, jvm.ColorScale.rgbToArray = function(rgb) {
    return rgb = rgb.substr(1), [ parseInt(rgb.substr(0, 2), 16), parseInt(rgb.substr(2, 2), 16), parseInt(rgb.substr(4, 2), 16) ];
}, jvm.Legend = function(params) {
    this.params = params || {}, this.map = this.params.map, this.series = this.params.series, 
    this.body = jvm.$("<div/>"), this.body.addClass("jvectormap-legend"), this.params.cssClass && this.body.addClass(this.params.cssClass), 
    params.vertical ? this.map.legendCntVertical.append(this.body) : this.map.legendCntHorizontal.append(this.body), 
    this.render();
}, jvm.Legend.prototype.render = function() {
    var i, tick, sample, label, ticks = this.series.scale.getTicks(), inner = jvm.$("<div/>").addClass("jvectormap-legend-inner");
    for (this.body.html(""), this.params.title && this.body.append(jvm.$("<div/>").addClass("jvectormap-legend-title").html(this.params.title)), 
    this.body.append(inner), i = 0; i < ticks.length; i++) {
        switch (tick = jvm.$("<div/>").addClass("jvectormap-legend-tick"), sample = jvm.$("<div/>").addClass("jvectormap-legend-tick-sample"), 
        this.series.params.attribute) {
          case "fill":
            jvm.isImageUrl(ticks[i].value) ? sample.css("background", "url(" + ticks[i].value + ")") : sample.css("background", ticks[i].value);
            break;

          case "stroke":
            sample.css("background", ticks[i].value);
            break;

          case "image":
            sample.css("background", "url(" + ticks[i].value + ") no-repeat center center");
            break;

          case "r":
            jvm.$("<div/>").css({
                "border-radius": ticks[i].value,
                border: this.map.params.markerStyle.initial["stroke-width"] + "px " + this.map.params.markerStyle.initial.stroke + " solid",
                width: 2 * ticks[i].value + "px",
                height: 2 * ticks[i].value + "px",
                background: this.map.params.markerStyle.initial.fill
            }).appendTo(sample);
        }
        tick.append(sample), label = ticks[i].label, this.params.labelRender && (label = this.params.labelRender(label)), 
        tick.append(jvm.$("<div>" + label + " </div>").addClass("jvectormap-legend-tick-text")), 
        inner.append(tick);
    }
    inner.append(jvm.$("<div/>").css("clear", "both"));
}, jvm.DataSeries = function(params, elements, map) {
    var scaleConstructor;
    params = params || {}, params.attribute = params.attribute || "fill", this.elements = elements, 
    this.params = params, this.map = map, params.attributes && this.setAttributes(params.attributes), 
    jvm.$.isArray(params.scale) ? (scaleConstructor = "fill" === params.attribute || "stroke" === params.attribute ? jvm.ColorScale : jvm.NumericScale, 
    this.scale = new scaleConstructor(params.scale, params.normalizeFunction, params.min, params.max)) : this.scale = params.scale ? new jvm.OrdinalScale(params.scale) : new jvm.SimpleScale(params.scale), 
    this.values = params.values || {}, this.setValues(this.values), this.params.legend && (this.legend = new jvm.Legend($.extend({
        map: this.map,
        series: this
    }, this.params.legend)));
}, jvm.DataSeries.prototype = {
    setAttributes: function(key, attr) {
        var code, attrs = key;
        if ("string" == typeof key) this.elements[key] && this.elements[key].setStyle(this.params.attribute, attr); else for (code in attrs) this.elements[code] && this.elements[code].element.setStyle(this.params.attribute, attrs[code]);
    },
    setValues: function(values) {
        var val, cc, max = -Number.MAX_VALUE, min = Number.MAX_VALUE, attrs = {};
        if (this.scale instanceof jvm.OrdinalScale || this.scale instanceof jvm.SimpleScale) for (cc in values) attrs[cc] = values[cc] ? this.scale.getValue(values[cc]) : this.elements[cc].element.style.initial[this.params.attribute]; else {
            if ("undefined" == typeof this.params.min || "undefined" == typeof this.params.max) for (cc in values) val = parseFloat(values[cc]), 
            val > max && (max = val), min > val && (min = val);
            "undefined" == typeof this.params.min ? (this.scale.setMin(min), this.params.min = min) : this.scale.setMin(this.params.min), 
            "undefined" == typeof this.params.max ? (this.scale.setMax(max), this.params.max = max) : this.scale.setMax(this.params.max);
            for (cc in values) "indexOf" != cc && (val = parseFloat(values[cc]), attrs[cc] = isNaN(val) ? this.elements[cc].element.style.initial[this.params.attribute] : this.scale.getValue(val));
        }
        this.setAttributes(attrs), jvm.$.extend(this.values, values);
    },
    clear: function() {
        var key, attrs = {};
        for (key in this.values) this.elements[key] && (attrs[key] = this.elements[key].element.shape.style.initial[this.params.attribute]);
        this.setAttributes(attrs), this.values = {};
    },
    setScale: function(scale) {
        this.scale.setScale(scale), this.values && this.setValues(this.values);
    },
    setNormalizeFunction: function(f) {
        this.scale.setNormalizeFunction(f), this.values && this.setValues(this.values);
    }
}, jvm.Proj = {
    degRad: 180 / Math.PI,
    radDeg: Math.PI / 180,
    radius: 6381372,
    sgn: function(n) {
        return n > 0 ? 1 : 0 > n ? -1 : n;
    },
    mill: function(lat, lng, c) {
        return {
            x: this.radius * (lng - c) * this.radDeg,
            y: -this.radius * Math.log(Math.tan((45 + .4 * lat) * this.radDeg)) / .8
        };
    },
    mill_inv: function(x, y, c) {
        return {
            lat: (2.5 * Math.atan(Math.exp(.8 * y / this.radius)) - 5 * Math.PI / 8) * this.degRad,
            lng: (c * this.radDeg + x / this.radius) * this.degRad
        };
    },
    merc: function(lat, lng, c) {
        return {
            x: this.radius * (lng - c) * this.radDeg,
            y: -this.radius * Math.log(Math.tan(Math.PI / 4 + lat * Math.PI / 360))
        };
    },
    merc_inv: function(x, y, c) {
        return {
            lat: (2 * Math.atan(Math.exp(y / this.radius)) - Math.PI / 2) * this.degRad,
            lng: (c * this.radDeg + x / this.radius) * this.degRad
        };
    },
    aea: function(lat, lng, c) {
        var fi0 = 0, lambda0 = c * this.radDeg, fi1 = 29.5 * this.radDeg, fi2 = 45.5 * this.radDeg, fi = lat * this.radDeg, lambda = lng * this.radDeg, n = (Math.sin(fi1) + Math.sin(fi2)) / 2, C = Math.cos(fi1) * Math.cos(fi1) + 2 * n * Math.sin(fi1), theta = n * (lambda - lambda0), ro = Math.sqrt(C - 2 * n * Math.sin(fi)) / n, ro0 = Math.sqrt(C - 2 * n * Math.sin(fi0)) / n;
        return {
            x: ro * Math.sin(theta) * this.radius,
            y: -(ro0 - ro * Math.cos(theta)) * this.radius
        };
    },
    aea_inv: function(xCoord, yCoord, c) {
        var x = xCoord / this.radius, y = yCoord / this.radius, fi0 = 0, lambda0 = c * this.radDeg, fi1 = 29.5 * this.radDeg, fi2 = 45.5 * this.radDeg, n = (Math.sin(fi1) + Math.sin(fi2)) / 2, C = Math.cos(fi1) * Math.cos(fi1) + 2 * n * Math.sin(fi1), ro0 = Math.sqrt(C - 2 * n * Math.sin(fi0)) / n, ro = Math.sqrt(x * x + (ro0 - y) * (ro0 - y)), theta = Math.atan(x / (ro0 - y));
        return {
            lat: Math.asin((C - ro * ro * n * n) / (2 * n)) * this.degRad,
            lng: (lambda0 + theta / n) * this.degRad
        };
    },
    lcc: function(lat, lng, c) {
        var fi0 = 0, lambda0 = c * this.radDeg, lambda = lng * this.radDeg, fi1 = 33 * this.radDeg, fi2 = 45 * this.radDeg, fi = lat * this.radDeg, n = Math.log(Math.cos(fi1) * (1 / Math.cos(fi2))) / Math.log(Math.tan(Math.PI / 4 + fi2 / 2) * (1 / Math.tan(Math.PI / 4 + fi1 / 2))), F = Math.cos(fi1) * Math.pow(Math.tan(Math.PI / 4 + fi1 / 2), n) / n, ro = F * Math.pow(1 / Math.tan(Math.PI / 4 + fi / 2), n), ro0 = F * Math.pow(1 / Math.tan(Math.PI / 4 + fi0 / 2), n);
        return {
            x: ro * Math.sin(n * (lambda - lambda0)) * this.radius,
            y: -(ro0 - ro * Math.cos(n * (lambda - lambda0))) * this.radius
        };
    },
    lcc_inv: function(xCoord, yCoord, c) {
        var x = xCoord / this.radius, y = yCoord / this.radius, fi0 = 0, lambda0 = c * this.radDeg, fi1 = 33 * this.radDeg, fi2 = 45 * this.radDeg, n = Math.log(Math.cos(fi1) * (1 / Math.cos(fi2))) / Math.log(Math.tan(Math.PI / 4 + fi2 / 2) * (1 / Math.tan(Math.PI / 4 + fi1 / 2))), F = Math.cos(fi1) * Math.pow(Math.tan(Math.PI / 4 + fi1 / 2), n) / n, ro0 = F * Math.pow(1 / Math.tan(Math.PI / 4 + fi0 / 2), n), ro = this.sgn(n) * Math.sqrt(x * x + (ro0 - y) * (ro0 - y)), theta = Math.atan(x / (ro0 - y));
        return {
            lat: (2 * Math.atan(Math.pow(F / ro, 1 / n)) - Math.PI / 2) * this.degRad,
            lng: (lambda0 + theta / n) * this.degRad
        };
    }
}, jvm.MapObject = function() {}, jvm.MapObject.prototype.getLabelText = function(key) {
    var text;
    return text = this.config.label ? "function" == typeof this.config.label.render ? this.config.label.render(key) : key : null;
}, jvm.MapObject.prototype.getLabelOffsets = function(key) {
    var offsets;
    return this.config.label && ("function" == typeof this.config.label.offsets ? offsets = this.config.label.offsets(key) : "object" == typeof this.config.label.offsets && (offsets = this.config.label.offsets[key])), 
    offsets || [ 0, 0 ];
}, jvm.MapObject.prototype.setHovered = function(isHovered) {
    this.isHovered !== isHovered && (this.isHovered = isHovered, this.shape.isHovered = isHovered, 
    this.shape.updateStyle(), this.label && (this.label.isHovered = isHovered, this.label.updateStyle()));
}, jvm.MapObject.prototype.setSelected = function(isSelected) {
    this.isSelected !== isSelected && (this.isSelected = isSelected, this.shape.isSelected = isSelected, 
    this.shape.updateStyle(), this.label && (this.label.isSelected = isSelected, this.label.updateStyle()), 
    jvm.$(this.shape).trigger("selected", [ isSelected ]));
}, jvm.MapObject.prototype.setStyle = function() {
    this.shape.setStyle.apply(this.shape, arguments);
}, jvm.MapObject.prototype.remove = function() {
    this.shape.remove(), this.label && this.label.remove();
}, jvm.Region = function(config) {
    var bbox, text, offsets;
    this.config = config, this.map = this.config.map, this.shape = config.canvas.addPath({
        d: config.path,
        "data-code": config.code
    }, config.style, config.canvas.rootElement), this.shape.addClass("jvectormap-region jvectormap-element"), 
    bbox = this.shape.getBBox(), text = this.getLabelText(config.code), this.config.label && text && (offsets = this.getLabelOffsets(config.code), 
    this.labelX = bbox.x + bbox.width / 2 + offsets[0], this.labelY = bbox.y + bbox.height / 2 + offsets[1], 
    this.label = config.canvas.addText({
        text: text,
        "text-anchor": "middle",
        "alignment-baseline": "central",
        x: this.labelX,
        y: this.labelY,
        "data-code": config.code
    }, config.labelStyle, config.labelsGroup), this.label.addClass("jvectormap-region jvectormap-element"));
}, jvm.inherits(jvm.Region, jvm.MapObject), jvm.Region.prototype.updateLabelPosition = function() {
    this.label && this.label.set({
        x: this.labelX * this.map.scale + this.map.transX * this.map.scale,
        y: this.labelY * this.map.scale + this.map.transY * this.map.scale
    });
}, jvm.Marker = function(config) {
    var text, offsets;
    this.config = config, this.map = this.config.map, this.isImage = !!this.config.style.initial.image, 
    this.createShape(), text = this.getLabelText(config.index), this.config.label && text && (offsets = this.getLabelOffsets(config.code), 
    this.labelX = (config.cx + offsets[0]) / this.map.scale - this.map.transX, this.labelY = (config.cy + offsets[1]) / this.map.scale - this.map.transY, 
    console.log(this.labelX, this.labelY), this.label = config.canvas.addText({
        text: text,
        "data-index": config.index,
        dy: "0.6ex",
        x: this.labelX,
        y: this.labelY
    }, config.labelStyle, config.labelsGroup), this.label.addClass("jvectormap-marker jvectormap-element"));
}, jvm.inherits(jvm.Marker, jvm.MapObject), jvm.Marker.prototype.createShape = function() {
    var that = this;
    this.shape && this.shape.remove(), this.shape = this.config.canvas[this.isImage ? "addImage" : "addCircle"]({
        "data-index": this.config.index,
        cx: this.config.cx,
        cy: this.config.cy
    }, this.config.style, this.config.group), this.shape.addClass("jvectormap-marker jvectormap-element"), 
    this.isImage && jvm.$(this.shape.node).on("imageloaded", function() {
        that.updateLabelPosition();
    });
}, jvm.Marker.prototype.updateLabelPosition = function() {
    this.label && this.label.set({
        x: this.labelX * this.map.scale + this.map.transX * this.map.scale + 5 + (this.isImage ? (this.shape.width || 0) / 2 : this.shape.properties.r),
        y: this.labelY * this.map.scale + this.map.transY * this.map.scale
    });
}, jvm.Marker.prototype.setStyle = function(property) {
    var isImage;
    jvm.Marker.parentClass.prototype.setStyle.apply(this, arguments), "r" === property && this.updateLabelPosition(), 
    isImage = !!this.shape.get("image"), isImage != this.isImage && (this.isImage = isImage, 
    this.config.style = jvm.$.extend(!0, {}, this.shape.style), this.createShape());
}, jvm.Map = function(params) {
    var e, map = this;
    if (this.params = jvm.$.extend(!0, {}, jvm.Map.defaultParams, params), !jvm.Map.maps[this.params.map]) throw new Error("Attempt to use map which was not loaded: " + this.params.map);
    this.mapData = jvm.Map.maps[this.params.map], this.markers = {}, this.regions = {}, 
    this.regionsColors = {}, this.regionsData = {}, this.container = jvm.$("<div>").addClass("jvectormap-container"), 
    this.params.container && this.params.container.append(this.container), this.container.data("mapObject", this), 
    this.defaultWidth = this.mapData.width, this.defaultHeight = this.mapData.height, 
    this.setBackgroundColor(this.params.backgroundColor), this.onResize = function() {
        map.updateSize();
    }, jvm.$(window).resize(this.onResize);
    for (e in jvm.Map.apiEvents) this.params[e] && this.container.bind(jvm.Map.apiEvents[e] + ".jvectormap", this.params[e]);
    this.canvas = new jvm.VectorCanvas(this.container[0], this.width, this.height), 
    ("ontouchstart" in window || window.DocumentTouch && document instanceof DocumentTouch) && this.params.bindTouchEvents && this.bindContainerTouchEvents(), 
    this.bindContainerEvents(), this.bindElementEvents(), this.createTip(), this.params.zoomButtons && this.bindZoomButtons(), 
    this.createRegions(), this.createMarkers(this.params.markers || {}), this.updateSize(), 
    this.params.focusOn && ("string" == typeof this.params.focusOn ? this.params.focusOn = {
        region: this.params.focusOn
    } : jvm.$.isArray(this.params.focusOn) && (this.params.focusOn = {
        regions: this.params.focusOn
    }), this.setFocus(this.params.focusOn)), this.params.selectedRegions && this.setSelectedRegions(this.params.selectedRegions), 
    this.params.selectedMarkers && this.setSelectedMarkers(this.params.selectedMarkers), 
    this.legendCntHorizontal = jvm.$("<div/>").addClass("jvectormap-legend-cnt jvectormap-legend-cnt-h"), 
    this.legendCntVertical = jvm.$("<div/>").addClass("jvectormap-legend-cnt jvectormap-legend-cnt-v"), 
    this.container.append(this.legendCntHorizontal), this.container.append(this.legendCntVertical), 
    this.params.series && this.createSeries();
}, jvm.Map.prototype = {
    transX: 0,
    transY: 0,
    scale: 1,
    baseTransX: 0,
    baseTransY: 0,
    baseScale: 1,
    width: 0,
    height: 0,
    setBackgroundColor: function(backgroundColor) {
        this.container.css("background-color", backgroundColor);
    },
    resize: function() {
        var curBaseScale = this.baseScale;
        this.width / this.height > this.defaultWidth / this.defaultHeight ? (this.baseScale = this.height / this.defaultHeight, 
        this.baseTransX = Math.abs(this.width - this.defaultWidth * this.baseScale) / (2 * this.baseScale)) : (this.baseScale = this.width / this.defaultWidth, 
        this.baseTransY = Math.abs(this.height - this.defaultHeight * this.baseScale) / (2 * this.baseScale)), 
        this.scale *= this.baseScale / curBaseScale, this.transX *= this.baseScale / curBaseScale, 
        this.transY *= this.baseScale / curBaseScale;
    },
    updateSize: function() {
        this.width = this.container.width(), this.height = this.container.height(), this.resize(), 
        this.canvas.setSize(this.width, this.height), this.applyTransform();
    },
    reset: function() {
        var key, i;
        for (key in this.series) for (i = 0; i < this.series[key].length; i++) this.series[key][i].clear();
        this.scale = this.baseScale, this.transX = this.baseTransX, this.transY = this.baseTransY, 
        this.applyTransform();
    },
    applyTransform: function() {
        var maxTransX, maxTransY, minTransX, minTransY;
        this.defaultWidth * this.scale <= this.width ? (maxTransX = (this.width - this.defaultWidth * this.scale) / (2 * this.scale), 
        minTransX = (this.width - this.defaultWidth * this.scale) / (2 * this.scale)) : (maxTransX = 0, 
        minTransX = (this.width - this.defaultWidth * this.scale) / this.scale), this.defaultHeight * this.scale <= this.height ? (maxTransY = (this.height - this.defaultHeight * this.scale) / (2 * this.scale), 
        minTransY = (this.height - this.defaultHeight * this.scale) / (2 * this.scale)) : (maxTransY = 0, 
        minTransY = (this.height - this.defaultHeight * this.scale) / this.scale), this.transY > maxTransY ? this.transY = maxTransY : this.transY < minTransY && (this.transY = minTransY), 
        this.transX > maxTransX ? this.transX = maxTransX : this.transX < minTransX && (this.transX = minTransX), 
        this.canvas.applyTransformParams(this.scale, this.transX, this.transY), this.markers && this.repositionMarkers(), 
        this.repositionLabels(), this.container.trigger("viewportChange", [ this.scale / this.baseScale, this.transX, this.transY ]);
    },
    bindContainerEvents: function() {
        var oldPageX, oldPageY, mouseDown = !1, map = this;
        this.params.panOnDrag && (this.container.mousemove(function(e) {
            return mouseDown && (map.transX -= (oldPageX - e.pageX) / map.scale, map.transY -= (oldPageY - e.pageY) / map.scale, 
            map.applyTransform(), oldPageX = e.pageX, oldPageY = e.pageY), !1;
        }).mousedown(function(e) {
            return mouseDown = !0, oldPageX = e.pageX, oldPageY = e.pageY, !1;
        }), this.onContainerMouseUp = function() {
            mouseDown = !1;
        }, jvm.$("body").mouseup(this.onContainerMouseUp)), this.params.zoomOnScroll && this.container.mousewheel(function(event) {
            var offset = jvm.$(map.container).offset(), centerX = event.pageX - offset.left, centerY = event.pageY - offset.top, zoomStep = Math.pow(1.003, event.deltaY);
            map.tip.hide(), map.setScale(map.scale * zoomStep, centerX, centerY), event.preventDefault();
        });
    },
    bindContainerTouchEvents: function() {
        var touchStartScale, touchStartDistance, touchX, touchY, centerTouchX, centerTouchY, lastTouchesLength, map = this, handleTouchEvent = function(e) {
            var offset, scale, transXOld, transYOld, touches = e.originalEvent.touches;
            "touchstart" == e.type && (lastTouchesLength = 0), 1 == touches.length ? (1 == lastTouchesLength && (transXOld = map.transX, 
            transYOld = map.transY, map.transX -= (touchX - touches[0].pageX) / map.scale, map.transY -= (touchY - touches[0].pageY) / map.scale, 
            map.applyTransform(), map.tip.hide(), (transXOld != map.transX || transYOld != map.transY) && e.preventDefault()), 
            touchX = touches[0].pageX, touchY = touches[0].pageY) : 2 == touches.length && (2 == lastTouchesLength ? (scale = Math.sqrt(Math.pow(touches[0].pageX - touches[1].pageX, 2) + Math.pow(touches[0].pageY - touches[1].pageY, 2)) / touchStartDistance, 
            map.setScale(touchStartScale * scale, centerTouchX, centerTouchY), map.tip.hide(), 
            e.preventDefault()) : (offset = jvm.$(map.container).offset(), centerTouchX = touches[0].pageX > touches[1].pageX ? touches[1].pageX + (touches[0].pageX - touches[1].pageX) / 2 : touches[0].pageX + (touches[1].pageX - touches[0].pageX) / 2, 
            centerTouchY = touches[0].pageY > touches[1].pageY ? touches[1].pageY + (touches[0].pageY - touches[1].pageY) / 2 : touches[0].pageY + (touches[1].pageY - touches[0].pageY) / 2, 
            centerTouchX -= offset.left, centerTouchY -= offset.top, touchStartScale = map.scale, 
            touchStartDistance = Math.sqrt(Math.pow(touches[0].pageX - touches[1].pageX, 2) + Math.pow(touches[0].pageY - touches[1].pageY, 2)))), 
            lastTouchesLength = touches.length;
        };
        jvm.$(this.container).bind("touchstart", handleTouchEvent), jvm.$(this.container).bind("touchmove", handleTouchEvent);
    },
    bindElementEvents: function() {
        var mouseMoved, map = this;
        this.container.mousemove(function() {
            mouseMoved = !0;
        }), this.container.delegate("[class~='jvectormap-element']", "mouseover mouseout", function(e) {
            var baseVal = jvm.$(this).attr("class").baseVal || jvm.$(this).attr("class"), type = -1 === baseVal.indexOf("jvectormap-region") ? "marker" : "region", code = jvm.$(this).attr("region" == type ? "data-code" : "data-index"), element = "region" == type ? map.regions[code].element : map.markers[code].element, tipText = "region" == type ? map.mapData.paths[code].name : map.markers[code].config.name || "", tipShowEvent = jvm.$.Event(type + "TipShow.jvectormap"), overEvent = jvm.$.Event(type + "Over.jvectormap");
            "mouseover" == e.type ? (map.container.trigger(overEvent, [ code ]), overEvent.isDefaultPrevented() || element.setHovered(!0), 
            map.tip.text(tipText), map.container.trigger(tipShowEvent, [ map.tip, code ]), tipShowEvent.isDefaultPrevented() || (map.tip.show(), 
            map.tipWidth = map.tip.width(), map.tipHeight = map.tip.height())) : (element.setHovered(!1), 
            map.tip.hide(), map.container.trigger(type + "Out.jvectormap", [ code ]));
        }), this.container.delegate("[class~='jvectormap-element']", "mousedown", function() {
            mouseMoved = !1;
        }), this.container.delegate("[class~='jvectormap-element']", "mouseup", function() {
            var baseVal = jvm.$(this).attr("class").baseVal ? jvm.$(this).attr("class").baseVal : jvm.$(this).attr("class"), type = -1 === baseVal.indexOf("jvectormap-region") ? "marker" : "region", code = jvm.$(this).attr("region" == type ? "data-code" : "data-index"), clickEvent = jvm.$.Event(type + "Click.jvectormap"), element = "region" == type ? map.regions[code].element : map.markers[code].element;
            mouseMoved || (map.container.trigger(clickEvent, [ code ]), ("region" === type && map.params.regionsSelectable || "marker" === type && map.params.markersSelectable) && (clickEvent.isDefaultPrevented() || (map.params[type + "sSelectableOne"] && map.clearSelected(type + "s"), 
            element.setSelected(!element.isSelected))));
        });
    },
    bindZoomButtons: function() {
        var map = this;
        jvm.$("<div/>").addClass("jvectormap-zoomin").text("+").appendTo(this.container), 
        jvm.$("<div/>").addClass("jvectormap-zoomout").html("&#x2212;").appendTo(this.container), 
        this.container.find(".jvectormap-zoomin").click(function() {
            map.setScale(map.scale * map.params.zoomStep, map.width / 2, map.height / 2, !1, map.params.zoomAnimate);
        }), this.container.find(".jvectormap-zoomout").click(function() {
            map.setScale(map.scale / map.params.zoomStep, map.width / 2, map.height / 2, !1, map.params.zoomAnimate);
        });
    },
    createTip: function() {
        var map = this;
        this.tip = jvm.$("<div/>").addClass("jvectormap-tip").appendTo(jvm.$("body")), this.container.mousemove(function(e) {
            var left = e.pageX - 15 - map.tipWidth, top = e.pageY - 15 - map.tipHeight;
            5 > left && (left = e.pageX + 15), 5 > top && (top = e.pageY + 15), map.tip.is(":visible") && map.tip.css({
                left: left,
                top: top
            });
        });
    },
    setScale: function(scale, anchorX, anchorY, isCentered, animate) {
        var interval, scaleStart, scaleDiff, transXStart, transXDiff, transYStart, transYDiff, transX, transY, viewportChangeEvent = jvm.$.Event("zoom.jvectormap"), that = this, i = 0, count = Math.abs(Math.round(60 * (scale - this.scale) / Math.max(scale, this.scale))), deferred = new jvm.$.Deferred();
        return scale > this.params.zoomMax * this.baseScale ? scale = this.params.zoomMax * this.baseScale : scale < this.params.zoomMin * this.baseScale && (scale = this.params.zoomMin * this.baseScale), 
        "undefined" != typeof anchorX && "undefined" != typeof anchorY && (zoomStep = scale / this.scale, 
        isCentered ? (transX = anchorX + this.defaultWidth * (this.width / (this.defaultWidth * scale)) / 2, 
        transY = anchorY + this.defaultHeight * (this.height / (this.defaultHeight * scale)) / 2) : (transX = this.transX - (zoomStep - 1) / scale * anchorX, 
        transY = this.transY - (zoomStep - 1) / scale * anchorY)), animate && count > 0 ? (scaleStart = this.scale, 
        scaleDiff = (scale - scaleStart) / count, transXStart = this.transX * this.scale, 
        transYStart = this.transY * this.scale, transXDiff = (transX * scale - transXStart) / count, 
        transYDiff = (transY * scale - transYStart) / count, interval = setInterval(function() {
            i += 1, that.scale = scaleStart + scaleDiff * i, that.transX = (transXStart + transXDiff * i) / that.scale, 
            that.transY = (transYStart + transYDiff * i) / that.scale, that.applyTransform(), 
            i == count && (clearInterval(interval), that.container.trigger(viewportChangeEvent, [ scale / that.baseScale ]), 
            deferred.resolve());
        }, 10)) : (this.transX = transX, this.transY = transY, this.scale = scale, this.applyTransform(), 
        this.container.trigger(viewportChangeEvent, [ scale / this.baseScale ]), deferred.resolve()), 
        deferred;
    },
    setFocus: function(config) {
        var bbox, itemBbox, newBbox, codes, i, point;
        if (config = config || {}, config.region ? codes = [ config.region ] : config.regions && (codes = config.regions), 
        codes) {
            for (i = 0; i < codes.length; i++) this.regions[codes[i]] && (itemBbox = this.regions[codes[i]].element.shape.getBBox(), 
            itemBbox && ("undefined" == typeof bbox ? bbox = itemBbox : (newBbox = {
                x: Math.min(bbox.x, itemBbox.x),
                y: Math.min(bbox.y, itemBbox.y),
                width: Math.max(bbox.x + bbox.width, itemBbox.x + itemBbox.width) - Math.min(bbox.x, itemBbox.x),
                height: Math.max(bbox.y + bbox.height, itemBbox.y + itemBbox.height) - Math.min(bbox.y, itemBbox.y)
            }, bbox = newBbox)));
            return this.setScale(Math.min(this.width / bbox.width, this.height / bbox.height), -(bbox.x + bbox.width / 2), -(bbox.y + bbox.height / 2), !0, config.animate);
        }
        return config.lat && config.lng ? (point = this.latLngToPoint(config.lat, config.lng), 
        config.x = this.transX - point.x / this.scale, config.y = this.transY - point.y / this.scale, 
        console.log(config.x, config.y)) : config.x && config.y && (config.x *= -this.defaultWidth, 
        config.y *= -this.defaultHeight), this.setScale(config.scale * this.baseScale, config.x, config.y, !0, config.animate);
    },
    getSelected: function(type) {
        var key, selected = [];
        for (key in this[type]) this[type][key].element.isSelected && selected.push(key);
        return selected;
    },
    getSelectedRegions: function() {
        return this.getSelected("regions");
    },
    getSelectedMarkers: function() {
        return this.getSelected("markers");
    },
    setSelected: function(type, keys) {
        var i;
        if ("object" != typeof keys && (keys = [ keys ]), jvm.$.isArray(keys)) for (i = 0; i < keys.length; i++) this[type][keys[i]].element.setSelected(!0); else for (i in keys) this[type][i].element.setSelected(!!keys[i]);
    },
    setSelectedRegions: function(keys) {
        this.setSelected("regions", keys);
    },
    setSelectedMarkers: function(keys) {
        this.setSelected("markers", keys);
    },
    clearSelected: function(type) {
        var i, select = {}, selected = this.getSelected(type);
        for (i = 0; i < selected.length; i++) select[selected[i]] = !1;
        this.setSelected(type, select);
    },
    clearSelectedRegions: function() {
        this.clearSelected("regions");
    },
    clearSelectedMarkers: function() {
        this.clearSelected("markers");
    },
    getMapObject: function() {
        return this;
    },
    getRegionName: function(code) {
        return this.mapData.paths[code].name;
    },
    createRegions: function() {
        var key, region, map = this;
        this.regionLabelsGroup = this.regionLabelsGroup || this.canvas.addGroup();
        for (key in this.mapData.paths) region = new jvm.Region({
            map: this,
            path: this.mapData.paths[key].path,
            code: key,
            style: jvm.$.extend(!0, {}, this.params.regionStyle),
            labelStyle: jvm.$.extend(!0, {}, this.params.regionLabelStyle),
            canvas: this.canvas,
            labelsGroup: this.regionLabelsGroup,
            label: "vml" != this.canvas.mode ? this.params.labels && this.params.labels.regions : null
        }), jvm.$(region.shape).bind("selected", function(e, isSelected) {
            map.container.trigger("regionSelected.jvectormap", [ jvm.$(this.node).attr("data-code"), isSelected, map.getSelectedRegions() ]);
        }), this.regions[key] = {
            element: region,
            config: this.mapData.paths[key]
        };
    },
    createMarkers: function(markers) {
        var i, marker, point, markerConfig, markersArray, map = this;
        if (this.markersGroup = this.markersGroup || this.canvas.addGroup(), this.markerLabelsGroup = this.markerLabelsGroup || this.canvas.addGroup(), 
        jvm.$.isArray(markers)) for (markersArray = markers.slice(), markers = {}, i = 0; i < markersArray.length; i++) markers[i] = markersArray[i];
        for (i in markers) markerConfig = markers[i] instanceof Array ? {
            latLng: markers[i]
        } : markers[i], point = this.getMarkerPosition(markerConfig), point !== !1 && (marker = new jvm.Marker({
            map: this,
            style: jvm.$.extend(!0, {}, this.params.markerStyle, {
                initial: markerConfig.style || {}
            }),
            labelStyle: jvm.$.extend(!0, {}, this.params.markerLabelStyle),
            index: i,
            cx: point.x,
            cy: point.y,
            group: this.markersGroup,
            canvas: this.canvas,
            labelsGroup: this.markerLabelsGroup,
            label: "vml" != this.canvas.mode ? this.params.labels && this.params.labels.markers : null
        }), jvm.$(marker.shape).bind("selected", function(e, isSelected) {
            map.container.trigger("markerSelected.jvectormap", [ jvm.$(this.node).attr("data-index"), isSelected, map.getSelectedMarkers() ]);
        }), this.markers[i] && this.removeMarkers([ i ]), this.markers[i] = {
            element: marker,
            config: markerConfig
        });
    },
    repositionMarkers: function() {
        var i, point;
        for (i in this.markers) point = this.getMarkerPosition(this.markers[i].config), 
        point !== !1 && this.markers[i].element.setStyle({
            cx: point.x,
            cy: point.y
        });
    },
    repositionLabels: function() {
        var key;
        for (key in this.regions) this.regions[key].element.updateLabelPosition();
        for (key in this.markers) this.markers[key].element.updateLabelPosition();
    },
    getMarkerPosition: function(markerConfig) {
        return jvm.Map.maps[this.params.map].projection ? this.latLngToPoint.apply(this, markerConfig.latLng || [ 0, 0 ]) : {
            x: markerConfig.coords[0] * this.scale + this.transX * this.scale,
            y: markerConfig.coords[1] * this.scale + this.transY * this.scale
        };
    },
    addMarker: function(key, marker, seriesData) {
        var values, i, markers = {}, data = [], seriesData = seriesData || [];
        for (markers[key] = marker, i = 0; i < seriesData.length; i++) values = {}, values[key] = seriesData[i], 
        data.push(values);
        this.addMarkers(markers, data);
    },
    addMarkers: function(markers, seriesData) {
        var i;
        for (seriesData = seriesData || [], this.createMarkers(markers), i = 0; i < seriesData.length; i++) this.series.markers[i].setValues(seriesData[i] || {});
    },
    removeMarkers: function(markers) {
        var i;
        for (i = 0; i < markers.length; i++) this.markers[markers[i]].element.remove(), 
        delete this.markers[markers[i]];
    },
    removeAllMarkers: function() {
        var i, markers = [];
        for (i in this.markers) markers.push(i);
        this.removeMarkers(markers);
    },
    latLngToPoint: function(lat, lng) {
        var point, inset, bbox, proj = jvm.Map.maps[this.params.map].projection, centralMeridian = proj.centralMeridian;
        return -180 + centralMeridian > lng && (lng += 360), point = jvm.Proj[proj.type](lat, lng, centralMeridian), 
        inset = this.getInsetForPoint(point.x, point.y), inset ? (bbox = inset.bbox, point.x = (point.x - bbox[0].x) / (bbox[1].x - bbox[0].x) * inset.width * this.scale, 
        point.y = (point.y - bbox[0].y) / (bbox[1].y - bbox[0].y) * inset.height * this.scale, 
        {
            x: point.x + this.transX * this.scale + inset.left * this.scale,
            y: point.y + this.transY * this.scale + inset.top * this.scale
        }) : !1;
    },
    pointToLatLng: function(x, y) {
        var i, inset, bbox, nx, ny, proj = jvm.Map.maps[this.params.map].projection, centralMeridian = proj.centralMeridian, insets = jvm.Map.maps[this.params.map].insets;
        for (i = 0; i < insets.length; i++) if (inset = insets[i], bbox = inset.bbox, nx = x - (this.transX * this.scale + inset.left * this.scale), 
        ny = y - (this.transY * this.scale + inset.top * this.scale), nx = nx / (inset.width * this.scale) * (bbox[1].x - bbox[0].x) + bbox[0].x, 
        ny = ny / (inset.height * this.scale) * (bbox[1].y - bbox[0].y) + bbox[0].y, nx > bbox[0].x && nx < bbox[1].x && ny > bbox[0].y && ny < bbox[1].y) return jvm.Proj[proj.type + "_inv"](nx, -ny, centralMeridian);
        return !1;
    },
    getInsetForPoint: function(x, y) {
        var i, bbox, insets = jvm.Map.maps[this.params.map].insets;
        for (i = 0; i < insets.length; i++) if (bbox = insets[i].bbox, x > bbox[0].x && x < bbox[1].x && y > bbox[0].y && y < bbox[1].y) return insets[i];
    },
    createSeries: function() {
        var i, key;
        this.series = {
            markers: [],
            regions: []
        };
        for (key in this.params.series) for (i = 0; i < this.params.series[key].length; i++) this.series[key][i] = new jvm.DataSeries(this.params.series[key][i], this[key], this);
    },
    remove: function() {
        this.tip.remove(), this.container.remove(), jvm.$(window).unbind("resize", this.onResize), 
        jvm.$("body").unbind("mouseup", this.onContainerMouseUp);
    }
}, jvm.Map.maps = {}, jvm.Map.defaultParams = {
    map: "world_mill_en",
    backgroundColor: "#505050",
    zoomButtons: !0,
    zoomOnScroll: !0,
    panOnDrag: !0,
    zoomMax: 8,
    zoomMin: 1,
    zoomStep: 1.6,
    zoomAnimate: !0,
    regionsSelectable: !1,
    markersSelectable: !1,
    bindTouchEvents: !0,
    regionStyle: {
        initial: {
            fill: "white",
            "fill-opacity": 1,
            stroke: "none",
            "stroke-width": 0,
            "stroke-opacity": 1
        },
        hover: {
            "fill-opacity": .8,
            cursor: "pointer"
        },
        selected: {
            fill: "yellow"
        },
        selectedHover: {}
    },
    regionLabelStyle: {
        initial: {
            "font-family": "Verdana",
            "font-size": "12",
            "font-weight": "bold",
            cursor: "default",
            fill: "black"
        },
        hover: {
            cursor: "pointer"
        }
    },
    markerStyle: {
        initial: {
            fill: "grey",
            stroke: "#505050",
            "fill-opacity": 1,
            "stroke-width": 1,
            "stroke-opacity": 1,
            r: 5
        },
        hover: {
            stroke: "black",
            "stroke-width": 2,
            cursor: "pointer"
        },
        selected: {
            fill: "blue"
        },
        selectedHover: {}
    },
    markerLabelStyle: {
        initial: {
            "font-family": "Verdana",
            "font-size": "12",
            "font-weight": "bold",
            cursor: "default",
            fill: "black"
        },
        hover: {
            cursor: "pointer"
        }
    }
}, jvm.Map.apiEvents = {
    onRegionTipShow: "regionTipShow",
    onRegionOver: "regionOver",
    onRegionOut: "regionOut",
    onRegionClick: "regionClick",
    onRegionSelected: "regionSelected",
    onMarkerTipShow: "markerTipShow",
    onMarkerOver: "markerOver",
    onMarkerOut: "markerOut",
    onMarkerClick: "markerClick",
    onMarkerSelected: "markerSelected",
    onViewportChange: "viewportChange"
};

jQuery.fn.vectorMap("addMap", "world_mill_en", {
    insets: [ {
        width: 900,
        top: 0,
        height: 440.70631074413296,
        bbox: [ {
            y: -12671671.123330014,
            x: -20004297.151525836
        }, {
            y: 6930392.025135122,
            x: 20026572.39474939
        } ],
        left: 0
    } ],
    paths: {
        BD: {
            path: "M651.84,230.21l-0.6,-2.0l-1.36,-1.71l-2.31,-0.11l-0.41,0.48l0.2,0.94l-0.53,0.99l-0.72,-0.36l-0.68,0.35l-1.2,-0.36l-0.37,-2.0l-0.81,-1.86l0.39,-1.46l-0.22,-0.47l-1.14,-0.53l0.29,-0.5l1.48,-0.94l0.03,-0.65l-1.55,-1.22l0.55,-1.14l1.61,0.94l1.04,0.15l0.18,1.54l0.34,0.35l5.64,0.63l-0.84,1.64l-1.22,0.34l-0.77,1.51l0.07,0.47l1.37,1.37l0.67,-0.19l0.42,-1.39l1.21,3.84l-0.03,1.21l-0.33,-0.15l-0.4,0.28Z",
            name: "Bangladesh"
        },
        BE: {
            path: "M429.29,144.05l1.91,0.24l2.1,-0.63l2.63,1.99l-0.21,1.66l-0.69,0.4l-0.18,1.2l-1.66,-1.13l-1.39,0.15l-2.73,-2.7l-1.17,-0.18l-0.16,-0.52l1.54,-0.5Z",
            name: "Belgium"
        },
        BF: {
            path: "M421.42,247.64l-0.11,0.95l0.34,1.16l1.4,1.71l0.07,1.1l0.32,0.37l2.55,0.51l-0.04,1.28l-0.38,0.53l-1.07,0.21l-0.72,1.18l-0.63,0.21l-3.22,-0.25l-0.94,0.39l-5.4,-0.05l-0.39,0.38l0.16,2.73l-1.23,-0.43l-1.17,0.1l-0.89,0.57l-2.27,-1.72l-0.13,-1.11l0.61,-0.96l0.02,-0.93l1.87,-1.98l0.44,-1.81l0.43,-0.39l1.28,0.26l1.05,-0.52l0.47,-0.73l1.84,-1.09l0.55,-0.83l2.2,-1.0l1.15,-0.3l0.72,0.45l1.13,-0.01Z",
            name: "Burkina Faso"
        },
        BG: {
            path: "M491.65,168.18l-0.86,0.88l-0.91,2.17l0.48,1.34l-1.6,-0.24l-2.55,0.95l-0.28,1.51l-1.8,0.22l-2.0,-1.0l-1.92,0.79l-1.42,-0.07l-0.15,-1.63l-1.05,-0.97l0.0,-0.8l1.2,-1.57l0.01,-0.56l-1.14,-1.23l-0.05,-0.94l0.88,0.97l0.88,-0.2l1.91,0.47l3.68,0.16l1.42,-0.81l2.72,-0.66l2.55,1.24Z",
            name: "Bulgaria"
        },
        BA: {
            path: "M463.49,163.65l2.1,0.5l1.72,-0.03l1.52,0.68l-0.36,0.78l0.08,0.45l1.04,1.02l-0.25,0.98l-1.81,1.15l-0.38,1.38l-1.67,-0.87l-0.89,-1.2l-2.11,-1.83l-1.63,-2.22l0.23,-0.57l0.48,0.38l0.55,-0.06l0.43,-0.51l0.94,-0.06Z",
            name: "Bosnia and Herz."
        },
        BN: {
            path: "M707.48,273.58l0.68,-0.65l1.41,-0.91l-0.15,1.63l-0.81,-0.05l-0.61,0.58l-0.53,-0.6Z",
            name: "Brunei"
        },
        BO: {
            path: "M263.83,340.69l-3.09,-0.23l-0.38,0.23l-0.7,1.52l-1.31,-1.53l-3.28,-0.64l-2.37,2.4l-1.31,0.26l-0.88,-3.26l-1.3,-2.86l0.74,-2.37l-0.13,-0.43l-1.2,-1.01l-0.37,-1.89l-1.08,-1.55l1.45,-2.56l-0.96,-2.33l0.47,-1.06l-0.34,-0.73l0.91,-1.32l0.16,-3.84l0.5,-1.18l-1.81,-3.41l2.46,0.07l0.8,-0.85l3.4,-1.91l2.66,-0.35l-0.19,1.38l0.3,1.07l-0.05,1.97l2.72,2.27l2.88,0.49l0.89,0.86l1.79,0.58l0.98,0.7l1.71,0.05l1.17,0.61l0.6,2.7l-0.7,0.54l0.96,2.99l0.37,0.28l4.3,0.1l-0.25,1.2l0.27,1.02l1.43,0.9l0.5,1.35l-0.41,1.86l-0.65,1.08l0.12,1.35l-2.69,-1.65l-2.4,-0.03l-4.36,0.76l-1.49,2.5l-0.11,1.52l-0.75,2.37Z",
            name: "Bolivia"
        },
        JP: {
            path: "M781.12,166.87l1.81,0.68l1.62,-0.97l0.39,2.42l-3.35,0.75l-2.23,2.88l-3.63,-1.9l-0.56,0.2l-1.26,3.05l-2.16,0.03l-0.29,-2.51l1.08,-2.03l2.45,-0.16l0.37,-0.33l1.25,-5.94l2.47,2.71l2.03,1.12ZM773.56,187.34l-0.91,2.22l0.37,1.52l-1.14,1.75l-3.02,1.26l-4.58,0.27l-3.34,3.01l-1.25,-0.8l-0.09,-1.9l-0.46,-0.38l-4.35,0.62l-3.0,1.32l-2.85,0.05l-0.37,0.27l0.13,0.44l2.32,1.89l-1.54,4.34l-1.26,0.9l-0.79,-0.7l0.56,-2.27l-0.21,-0.45l-1.47,-0.75l-0.74,-1.4l2.12,-0.84l1.26,-1.7l2.45,-1.42l1.83,-1.91l4.78,-0.81l2.6,0.57l0.44,-0.21l2.39,-4.66l1.29,1.06l0.5,0.01l5.1,-4.02l1.69,-3.73l-0.38,-3.4l0.9,-1.61l2.14,-0.44l1.23,3.72l-0.07,2.18l-2.23,2.84l-0.04,3.16ZM757.78,196.26l0.19,0.56l-1.01,1.21l-1.16,-0.68l-1.28,0.65l-0.69,1.45l-1.02,-0.5l0.01,-0.93l1.14,-1.38l1.57,0.14l0.85,-0.98l1.4,0.46Z",
            name: "Japan"
        },
        BI: {
            path: "M495.45,295.49l-1.08,-2.99l1.14,-0.11l0.64,-1.19l0.76,0.09l0.65,1.83l-2.1,2.36Z",
            name: "Burundi"
        },
        BJ: {
            path: "M429.57,255.75l-0.05,0.8l0.5,1.34l-0.42,0.86l0.17,0.79l-1.81,2.12l-0.57,1.76l-0.08,5.42l-1.41,0.2l-0.48,-1.36l0.11,-5.71l-0.52,-0.7l-0.2,-1.35l-1.48,-1.48l0.21,-0.9l0.89,-0.43l0.42,-0.92l1.27,-0.36l1.22,-1.34l0.61,-0.0l1.62,1.24Z",
            name: "Benin"
        },
        BT: {
            path: "M650.32,213.86l0.84,0.71l-0.12,1.1l-3.76,-0.11l-1.57,0.4l-1.93,-0.87l1.48,-1.96l1.13,-0.57l1.63,0.57l1.33,0.08l0.99,0.65Z",
            name: "Bhutan"
        },
        JM: {
            path: "M228.38,239.28l-0.8,0.4l-2.26,-1.06l0.84,-0.23l2.14,0.3l1.17,0.56l-1.08,0.03Z",
            name: "Jamaica"
        },
        BW: {
            path: "M483.92,330.07l2.27,4.01l2.83,2.86l0.96,0.31l0.78,2.43l2.13,0.61l1.02,0.76l-3.0,1.64l-2.32,2.02l-1.54,2.69l-1.52,0.45l-0.64,1.94l-1.34,0.52l-1.85,-0.12l-1.21,-0.74l-1.35,-0.3l-1.22,0.62l-0.75,1.37l-2.31,1.9l-1.4,0.21l-0.35,-0.59l0.16,-1.75l-1.48,-2.54l-0.62,-0.43l-0.0,-7.1l2.08,-0.08l0.39,-0.4l0.07,-8.9l5.19,-0.93l0.8,0.89l0.51,0.07l1.5,-0.95l2.21,-0.49Z",
            name: "Botswana"
        },
        BR: {
            path: "M259.98,275.05l3.24,0.7l0.65,-0.53l4.55,-1.32l1.08,-1.06l-0.02,-0.63l0.55,-0.05l0.28,0.28l-0.26,0.87l0.22,0.48l0.73,0.32l0.4,0.81l-0.62,0.86l-0.4,2.13l0.82,2.56l1.69,1.43l1.43,0.2l3.17,-1.68l3.18,0.3l0.65,-0.75l-0.27,-0.92l1.9,-0.09l2.39,0.99l1.06,-0.61l0.84,0.78l1.2,-0.18l1.18,-1.06l0.84,-1.94l1.36,-2.11l0.37,-0.05l1.89,5.45l1.33,0.59l0.05,1.28l-1.77,1.94l0.02,0.56l1.02,0.87l4.07,0.36l0.08,2.16l0.66,0.29l1.74,-1.5l6.97,2.32l1.02,1.22l-0.35,1.18l0.49,0.5l2.81,-0.74l4.77,1.3l3.75,-0.08l3.57,2.0l3.29,2.86l1.93,0.72l2.12,0.12l0.71,0.62l1.21,4.51l-0.95,3.98l-4.72,5.06l-1.64,2.92l-1.72,2.05l-0.8,0.3l-0.72,2.03l0.18,4.75l-0.94,5.53l-0.81,1.13l-0.43,3.36l-2.55,3.5l-0.4,2.51l-1.86,1.04l-0.67,1.53l-2.54,0.01l-3.94,1.01l-1.83,1.2l-2.87,0.82l-3.03,2.19l-2.2,2.83l-0.36,2.0l0.4,1.58l-0.44,2.6l-0.51,1.2l-1.77,1.54l-2.75,4.78l-3.83,3.42l-1.24,2.74l-1.18,1.15l-0.36,-0.83l0.95,-1.14l0.01,-0.5l-1.52,-1.97l-4.56,-3.32l-1.03,-0.0l-2.38,-2.02l-0.81,-0.0l5.34,-5.45l3.77,-2.58l0.22,-2.46l-1.35,-1.81l-0.91,0.07l0.58,-2.33l0.01,-1.54l-1.11,-0.83l-1.75,0.3l-0.44,-3.11l-0.52,-0.95l-1.88,-0.88l-1.24,0.47l-2.17,-0.41l0.15,-3.21l-0.62,-1.34l0.66,-0.73l-0.22,-1.34l0.66,-1.13l0.44,-2.04l-0.61,-1.83l-1.4,-0.86l-0.2,-0.75l0.34,-1.39l-0.38,-0.5l-4.52,-0.1l-0.72,-2.22l0.59,-0.42l-0.03,-1.1l-0.5,-0.87l-0.32,-1.7l-1.45,-0.76l-1.63,-0.02l-1.05,-0.72l-1.6,-0.48l-1.13,-0.99l-2.69,-0.4l-2.47,-2.06l0.13,-4.35l-0.45,-0.45l-3.46,0.5l-3.44,1.94l-0.6,0.74l-2.9,-0.17l-1.47,0.42l-0.72,-0.18l0.15,-3.52l-0.63,-0.34l-1.94,1.41l-1.87,-0.06l-0.83,-1.18l-1.37,-0.26l0.21,-1.01l-1.35,-1.49l-0.88,-1.91l0.56,-0.6l-0.0,-0.81l1.29,-0.62l0.22,-0.43l-0.22,-1.19l0.61,-0.91l0.15,-0.99l2.65,-1.58l1.99,-0.47l0.42,-0.36l2.06,0.11l0.42,-0.33l1.19,-8.0l-0.41,-1.56l-1.1,-1.0l0.01,-1.33l1.91,-0.42l0.08,-0.96l-0.33,-0.43l-1.14,-0.2l-0.02,-0.83l4.47,0.05l0.82,-0.67l0.82,1.81l0.8,0.07l1.15,1.1l2.26,-0.05l0.71,-0.83l2.78,-0.96l0.48,-1.13l1.6,-0.64l0.24,-0.47l-0.48,-0.82l-1.83,-0.19l-0.36,-3.22Z",
            name: "Brazil"
        },
        BS: {
            path: "M226.4,223.87l-0.48,-1.15l-0.84,-0.75l0.36,-1.11l0.95,1.95l0.01,1.06ZM225.56,216.43l-1.87,0.29l-0.04,-0.22l0.74,-0.14l1.17,0.06Z",
            name: "Bahamas"
        },
        BY: {
            path: "M493.84,128.32l0.29,0.7l0.49,0.23l1.19,-0.38l2.09,0.72l0.19,1.26l-0.45,1.24l1.57,2.26l0.89,0.59l0.17,0.81l1.58,0.56l0.4,0.5l-0.53,0.41l-1.87,-0.11l-0.73,0.38l-0.13,0.52l1.04,2.74l-1.91,0.26l-0.89,0.99l-0.11,1.18l-2.73,-0.04l-0.53,-0.62l-0.52,-0.08l-0.75,0.46l-0.91,-0.42l-1.92,-0.07l-2.75,-0.79l-2.6,-0.28l-2.0,0.07l-1.5,0.92l-0.67,0.07l-0.08,-1.22l-0.59,-1.19l1.36,-0.88l0.01,-1.35l-0.7,-1.41l-0.07,-1.0l2.16,-0.02l2.72,-1.3l0.75,-2.04l1.91,-1.04l0.2,-0.41l-0.19,-1.25l3.8,-1.78l2.3,0.77Z",
            name: "Belarus"
        },
        BZ: {
            path: "M198.03,244.38l0.1,-4.49l0.69,-0.06l0.74,-1.3l0.34,0.28l-0.4,1.3l0.17,0.58l-0.34,2.25l-1.3,1.42Z",
            name: "Belize"
        },
        RU: {
            path: "M491.55,115.25l2.55,-1.85l-0.01,-0.65l-2.2,-1.5l7.32,-6.76l1.03,-2.11l-0.13,-0.49l-3.46,-2.52l0.86,-2.7l-2.11,-2.81l1.56,-3.67l-2.77,-4.52l2.15,-2.99l-0.08,-0.55l-3.65,-2.73l0.3,-2.54l1.81,-0.37l4.26,-1.77l2.42,-1.45l4.06,2.61l6.79,1.04l9.34,4.85l1.78,1.88l0.14,2.46l-2.55,2.02l-3.9,1.06l-11.07,-3.14l-2.06,0.53l-0.13,0.7l3.94,2.94l0.31,5.86l0.26,0.36l5.14,2.24l0.58,-0.29l0.32,-1.94l-1.35,-1.78l1.13,-1.09l6.13,2.42l2.11,-0.98l0.18,-0.56l-1.51,-2.67l5.41,-3.76l2.07,0.22l2.26,1.41l0.57,-0.16l1.46,-2.87l-0.05,-0.44l-1.92,-2.32l1.12,-2.32l-1.32,-2.27l5.87,1.16l1.04,1.75l-2.59,0.43l-0.33,0.4l0.02,2.36l2.46,1.83l3.87,-0.91l0.86,-2.8l13.69,-5.65l0.99,0.11l-1.92,2.06l0.23,0.67l3.11,0.45l2.0,-1.48l4.56,-0.12l3.64,-1.73l2.65,2.44l0.56,-0.01l2.85,-2.88l-0.01,-0.57l-2.35,-2.29l0.9,-1.01l7.14,1.3l3.41,1.36l9.05,4.97l0.51,-0.11l1.67,-2.27l-0.05,-0.53l-2.43,-2.21l-0.06,-0.78l-0.34,-0.36l-2.52,-0.36l0.64,-1.93l-1.32,-3.46l-0.06,-1.21l4.48,-4.06l1.69,-4.29l1.6,-0.81l6.23,1.18l0.44,2.21l-2.29,3.64l0.06,0.5l1.47,1.39l0.76,3.0l-0.56,6.03l2.69,2.82l-0.96,2.57l-4.86,5.95l0.23,0.64l2.86,0.61l0.42,-0.17l0.93,-1.4l2.64,-1.03l0.87,-2.24l2.09,-1.96l0.07,-0.5l-1.36,-2.28l1.09,-2.69l-0.32,-0.55l-2.47,-0.33l-0.5,-2.06l1.94,-4.38l-0.06,-0.42l-2.96,-3.4l4.12,-2.88l0.16,-0.4l-0.51,-2.93l0.54,-0.05l1.13,2.25l-0.96,4.35l0.27,0.47l2.68,0.84l0.5,-0.51l-1.02,-2.99l3.79,-1.66l5.01,-0.24l4.53,2.61l0.48,-0.06l0.07,-0.48l-2.18,-3.82l-0.23,-4.67l3.98,-0.9l5.97,0.21l5.49,-0.64l0.27,-0.65l-1.83,-2.31l2.56,-2.9l2.87,-0.17l4.8,-2.47l6.54,-0.67l1.03,-1.42l6.25,-0.45l2.32,1.11l5.53,-2.7l4.5,0.08l0.39,-0.28l0.66,-2.15l2.26,-2.12l5.69,-2.11l3.21,1.29l-2.46,0.94l-0.25,0.42l0.34,0.35l5.41,0.77l0.61,2.33l0.58,0.25l2.2,-1.22l7.13,0.07l5.51,2.47l1.79,1.72l-0.53,2.24l-9.16,4.15l-1.97,1.52l0.16,0.71l6.77,1.91l2.16,-0.78l1.13,2.74l0.67,0.11l1.01,-1.15l3.81,-0.73l7.7,0.77l0.54,1.99l0.36,0.29l10.47,0.71l0.43,-0.38l0.13,-3.23l4.87,0.78l3.95,-0.02l3.83,2.4l1.03,2.71l-1.35,1.79l0.02,0.5l3.15,3.64l4.07,1.96l0.53,-0.18l2.23,-4.47l3.95,1.93l4.16,-1.21l4.73,1.39l2.05,-1.26l3.94,0.62l0.43,-0.55l-1.68,-4.02l2.89,-1.8l22.31,3.03l2.16,2.75l6.55,3.51l10.29,-0.81l4.82,0.73l1.85,1.66l-0.29,3.08l0.25,0.41l3.08,1.26l3.56,-0.88l4.35,-0.11l4.8,0.87l4.57,-0.47l4.23,3.79l0.43,0.07l3.1,-1.4l0.16,-0.6l-1.88,-2.62l0.85,-1.52l7.71,1.21l5.22,-0.26l7.09,2.09l9.59,5.22l6.35,4.11l-0.2,2.38l1.88,1.41l0.6,-0.42l-0.48,-2.53l6.15,0.57l4.4,3.51l-1.97,1.43l-4.0,0.41l-0.36,0.39l-0.06,3.79l-0.74,0.62l-2.07,-0.11l-1.91,-1.39l-3.14,-1.11l-0.78,-1.85l-2.72,-0.68l-2.63,0.49l-1.04,-1.1l0.46,-1.31l-0.5,-0.51l-3.0,0.98l-0.22,0.58l0.99,1.7l-1.21,1.48l-3.04,1.68l-3.12,-0.28l-0.4,0.23l0.09,0.46l2.2,2.09l1.46,3.2l1.15,1.1l0.24,1.33l-0.42,0.67l-4.63,-0.77l-6.96,2.9l-2.19,0.44l-7.6,5.06l-0.84,1.45l-3.61,-2.37l-6.24,2.82l-0.94,-1.15l-0.53,-0.08l-2.28,1.52l-3.2,-0.49l-0.44,0.27l-0.78,2.37l-3.05,3.78l0.09,1.47l0.29,0.36l2.54,0.72l-0.29,4.53l-1.97,0.11l-0.35,0.26l-1.07,2.94l0.8,1.45l-3.91,1.58l-1.05,3.95l-3.48,0.77l-0.3,0.3l-0.72,3.29l-3.09,2.65l-0.7,-1.74l-2.44,-12.44l1.16,-4.71l2.04,-2.06l0.22,-1.64l3.8,-0.86l4.46,-4.61l4.28,-3.81l4.48,-3.01l2.17,-5.63l-0.42,-0.54l-3.04,0.33l-1.77,3.31l-5.86,3.86l-1.86,-4.25l-0.45,-0.23l-6.46,1.3l-6.47,6.44l-0.01,0.55l1.58,1.74l-8.24,1.17l0.15,-2.2l-0.34,-0.42l-3.89,-0.56l-3.25,1.81l-7.62,-0.62l-8.45,1.19l-17.71,15.41l0.22,0.7l3.74,0.41l1.36,2.17l2.43,0.76l1.88,-1.68l2.4,0.2l3.4,3.54l0.08,2.6l-1.95,3.42l-0.21,3.9l-1.1,5.06l-3.71,4.54l-0.87,2.21l-8.29,8.89l-3.19,1.7l-1.32,0.03l-1.45,-1.36l-0.49,-0.04l-2.27,1.5l0.41,-3.65l-0.59,-2.47l1.75,-0.89l2.91,0.53l0.42,-0.2l1.68,-3.03l0.87,-3.46l0.97,-1.18l1.32,-2.88l-0.45,-0.56l-4.14,0.95l-2.19,1.25l-3.41,-0.0l-1.06,-2.93l-2.97,-2.3l-4.28,-1.06l-1.75,-5.07l-2.66,-5.01l-2.29,-1.29l-3.75,-1.01l-3.44,0.08l-3.18,0.62l-2.24,1.77l0.05,0.66l1.18,0.69l0.02,1.43l-1.33,1.05l-2.26,3.51l-0.04,1.43l-3.16,1.84l-2.82,-1.16l-3.01,0.23l-1.35,-1.07l-1.5,-0.35l-3.9,2.31l-3.22,0.52l-2.27,0.79l-3.05,-0.51l-2.21,0.03l-1.48,-1.6l-2.6,-1.63l-2.63,-0.43l-5.46,1.01l-3.23,-1.25l-0.72,-2.57l-5.2,-1.24l-2.75,-1.36l-0.5,0.12l-2.59,3.45l0.84,2.1l-2.06,1.93l-3.41,-0.77l-2.42,-0.12l-1.83,-1.54l-2.53,-0.05l-2.42,-0.98l-3.86,1.57l-4.72,2.78l-3.3,0.75l-1.55,-1.92l-3.0,0.41l-1.11,-1.33l-1.62,-0.59l-1.31,-1.94l-1.38,-0.6l-3.7,0.79l-3.31,-1.83l-0.51,0.11l-0.99,1.29l-5.29,-8.05l-2.96,-2.48l0.65,-0.77l0.01,-0.51l-0.5,-0.11l-6.2,3.21l-1.84,0.15l0.15,-1.39l-0.26,-0.42l-3.22,-1.17l-2.46,0.7l-0.69,-3.16l-0.32,-0.31l-4.5,-0.75l-2.47,1.47l-6.19,1.27l-1.29,0.86l-9.51,1.3l-1.15,1.17l-0.03,0.53l1.47,1.9l-1.89,0.69l-0.22,0.56l0.31,0.6l-2.11,1.44l0.03,0.68l3.75,2.12l-0.39,0.98l-3.23,-0.13l-0.86,0.86l-3.09,-1.59l-3.97,0.07l-2.66,1.35l-8.32,-3.56l-4.07,0.06l-5.39,3.68l-0.39,2.0l-2.03,-1.5l-0.59,0.13l-2.0,3.59l0.57,0.93l-1.28,2.16l0.06,0.48l2.13,2.17l1.95,0.04l1.37,1.82l-0.23,1.46l0.25,0.43l0.83,0.33l-0.8,1.31l-2.49,0.62l-2.49,3.2l0.0,0.49l2.17,2.78l-0.15,2.18l2.5,3.24l-1.58,1.59l-0.7,-0.13l-1.63,-1.72l-2.29,-0.84l-0.94,-1.31l-2.34,-0.63l-1.48,0.4l-0.43,-0.47l-3.51,-1.48l-5.76,-1.01l-0.45,0.19l-2.89,-2.34l-2.9,-1.2l-1.53,-1.29l1.29,-0.43l2.08,-2.61l-0.05,-0.55l-0.89,-0.79l3.05,-1.06l0.27,-0.42l-0.07,-0.69l-0.49,-0.35l-1.73,0.39l0.04,-0.68l1.04,-0.72l2.66,-0.48l0.4,-1.32l-0.5,-1.6l0.92,-1.54l0.03,-1.17l-0.29,-0.37l-3.69,-1.06l-1.41,0.02l-1.42,-1.41l-2.19,0.38l-2.77,-1.01l-0.03,-0.59l-0.89,-1.43l-2.0,-0.32l-0.11,-0.54l0.49,-0.53l0.01,-0.53l-1.6,-1.9l-3.58,0.02l-0.88,0.73l-0.46,-0.07l-1.0,-2.79l2.22,-0.02l0.97,-0.74l0.07,-0.57l-0.9,-1.04l-1.35,-0.48l-0.11,-0.7l-0.95,-0.58l-1.38,-1.99l0.46,-0.98l-0.51,-1.96l-2.45,-0.84l-1.21,0.3l-0.46,-0.76l-2.46,-0.83l-0.72,-1.87l-0.21,-1.69l-0.99,-0.85l0.85,-1.17l-0.7,-3.21l1.66,-1.97l-0.16,-0.79ZM749.2,170.72l-0.6,0.4l-0.13,0.16l-0.01,-0.51l0.74,-0.05ZM874.85,67.94l-5.63,0.48l-0.26,-0.84l3.15,-1.89l1.94,0.01l3.19,1.16l-2.39,1.09ZM797.39,48.49l-2.0,1.36l-3.8,-0.42l-4.25,-1.8l0.35,-0.97l9.69,1.83ZM783.67,46.12l-1.63,3.09l-8.98,-0.13l-4.09,1.14l-4.54,-2.97l1.16,-3.01l3.05,-0.89l6.5,0.22l8.54,2.56ZM778.2,134.98l-0.56,-0.9l0.27,-0.12l0.29,1.01ZM778.34,135.48l0.94,3.53l-0.05,3.38l1.05,3.39l2.18,5.0l-2.89,-0.83l-0.49,0.26l-1.54,4.65l2.42,3.5l-0.04,1.13l-1.24,-1.24l-0.61,0.06l-1.09,1.61l-0.28,-1.61l0.27,-3.1l-0.28,-3.4l0.58,-2.47l0.11,-4.39l-1.46,-3.36l0.21,-4.32l2.15,-1.46l0.07,-0.34ZM771.95,56.61l1.76,-1.42l2.89,-0.42l3.28,1.71l0.14,0.6l-3.27,0.03l-4.81,-0.5ZM683.76,31.09l-13.01,1.93l4.03,-6.35l1.82,-0.56l1.73,0.34l5.99,2.98l-0.56,1.66ZM670.85,27.93l-5.08,0.64l-6.86,-1.57l-3.99,-2.05l-2.1,-4.16l-2.6,-0.87l5.72,-3.5l5.2,-1.28l4.69,2.85l5.59,5.4l-0.56,4.53ZM564.15,68.94l-0.64,0.17l-7.85,-0.57l-0.86,-2.04l-4.28,-1.17l-0.28,-1.94l2.27,-0.89l0.25,-0.39l-0.08,-2.38l4.81,-3.97l-0.15,-0.7l-1.47,-0.38l5.3,-3.81l0.15,-0.44l-0.58,-1.94l5.28,-2.51l8.21,-3.27l8.28,-0.96l4.35,-1.94l4.6,-0.64l1.36,1.61l-1.34,1.28l-16.43,4.94l-7.97,4.88l-7.74,9.63l0.66,4.14l4.16,3.27ZM548.81,18.48l-5.5,1.18l-0.58,1.02l-2.59,0.84l-2.13,-1.07l1.12,-1.42l-0.3,-0.65l-2.33,-0.07l1.68,-0.36l3.47,-0.06l0.42,1.29l0.66,0.16l1.38,-1.34l2.15,-0.88l2.94,1.01l-0.39,0.36ZM477.37,133.15l-4.08,0.05l-2.56,-0.32l0.33,-0.87l3.17,-1.03l3.24,0.96l-0.09,1.23Z",
            name: "Russia"
        },
        RW: {
            path: "M497.0,288.25l0.71,1.01l-0.11,1.09l-1.63,0.03l-1.04,1.39l-0.83,-0.11l0.51,-1.2l0.08,-1.34l0.42,-0.41l0.7,0.14l1.19,-0.61Z",
            name: "Rwanda"
        },
        RS: {
            path: "M469.4,163.99l0.42,-0.5l-0.01,-0.52l-1.15,-1.63l1.43,-0.62l1.33,0.12l1.17,1.06l0.46,1.13l1.34,0.64l0.35,1.35l1.46,0.9l0.76,-0.29l0.2,0.69l-0.48,0.78l0.22,1.12l1.05,1.22l-0.77,0.8l-0.37,1.52l-1.21,0.08l0.24,-0.64l-0.39,-0.54l-2.08,-1.64l-0.9,0.05l-0.48,0.94l-2.12,-1.37l0.53,-1.6l-1.11,-1.37l0.51,-1.1l-0.41,-0.57Z",
            name: "Serbia"
        },
        LT: {
            path: "M486.93,129.3l0.17,1.12l-1.81,0.98l-0.72,2.02l-2.47,1.18l-2.1,-0.02l-0.73,-1.05l-1.06,-0.3l-0.09,-1.87l-3.56,-1.13l-0.43,-2.36l2.48,-0.94l4.12,0.22l2.25,-0.31l0.52,0.69l1.24,0.21l2.19,1.56Z",
            name: "Lithuania"
        },
        LU: {
            path: "M436.08,149.45l-0.48,-0.07l0.3,-1.28l0.27,0.4l-0.09,0.96Z",
            name: "Luxembourg"
        },
        LR: {
            path: "M399.36,265.97l0.18,1.54l-0.48,0.99l0.08,0.47l2.47,1.8l-0.33,2.8l-2.65,-1.13l-5.78,-4.61l0.58,-1.32l2.1,-2.33l0.86,-0.22l0.77,1.14l-0.14,0.85l0.59,0.87l1.0,0.14l0.76,-0.99Z",
            name: "Liberia"
        },
        RO: {
            path: "M487.53,154.23l0.6,0.24l2.87,3.98l-0.17,2.69l0.45,1.42l1.32,0.81l1.35,-0.42l0.76,0.36l0.02,0.31l-0.83,0.45l-0.59,-0.22l-0.54,0.3l-0.62,3.3l-1.0,-0.22l-2.07,-1.13l-2.95,0.71l-1.25,0.76l-3.51,-0.15l-1.89,-0.47l-0.87,0.16l-0.82,-1.3l0.29,-0.26l-0.06,-0.64l-1.09,-0.34l-0.56,0.5l-1.05,-0.64l-0.39,-1.39l-1.36,-0.65l-0.35,-1.0l-0.83,-0.75l1.54,-0.54l2.66,-4.21l2.4,-1.24l2.96,0.34l1.48,0.73l0.79,-0.45l1.78,-0.3l0.75,-0.74l0.79,0.0Z",
            name: "Romania"
        },
        GW: {
            path: "M386.23,253.6l-0.29,0.84l0.15,0.6l-2.21,0.59l-0.86,0.96l-1.04,-0.83l-1.09,-0.23l-0.54,-1.06l-0.66,-0.49l2.41,-0.48l4.13,0.1Z",
            name: "Guinea-Bissau"
        },
        GT: {
            path: "M195.08,249.77l-2.48,-0.37l-1.03,-0.45l-1.14,-0.89l0.3,-0.99l-0.24,-0.68l0.96,-1.66l2.98,-0.01l0.4,-0.37l-0.19,-1.28l-1.67,-1.4l0.51,-0.4l0.0,-1.05l3.85,0.02l-0.21,4.53l0.4,0.43l1.46,0.38l-1.48,0.98l-0.35,0.7l0.12,0.57l-2.2,1.96Z",
            name: "Guatemala"
        },
        GR: {
            path: "M487.07,174.59l-0.59,1.43l-0.37,0.21l-2.84,-0.35l-3.03,0.77l-0.18,0.68l1.28,1.23l-0.61,0.23l-1.14,0.0l-1.2,-1.39l-0.63,0.03l-0.53,1.01l0.56,1.76l1.03,1.19l-0.56,0.38l-0.05,0.62l2.52,2.12l0.02,0.87l-1.78,-0.59l-0.48,0.56l0.5,1.0l-1.07,0.2l-0.3,0.53l0.75,2.01l-0.98,0.02l-1.84,-1.12l-1.37,-4.2l-2.21,-2.95l-0.11,-0.56l1.04,-1.28l0.2,-0.95l0.85,-0.66l0.03,-0.46l1.32,-0.21l1.01,-0.64l1.22,0.05l0.65,-0.56l2.26,-0.0l1.82,-0.75l1.85,1.0l2.28,-0.28l0.35,-0.39l0.01,-0.77l0.34,0.22ZM480.49,192.16l0.58,0.4l-0.68,-0.12l0.11,-0.28ZM482.52,192.82l2.51,0.06l0.24,0.32l-1.99,0.13l-0.77,-0.51Z",
            name: "Greece"
        },
        GQ: {
            path: "M448.79,279.62l0.02,2.22l-4.09,0.0l0.69,-2.27l3.38,0.05Z",
            name: "Eq. Guinea"
        },
        GY: {
            path: "M277.42,270.07l-0.32,1.83l-1.32,0.57l-0.23,0.46l-0.28,2.0l1.11,1.82l0.83,0.19l0.32,1.25l1.13,1.62l-1.21,-0.19l-1.08,0.71l-1.77,0.5l-0.44,0.46l-0.86,-0.09l-1.32,-1.01l-0.77,-2.27l0.36,-1.9l0.68,-1.23l-0.57,-1.17l-0.74,-0.43l0.12,-1.16l-0.9,-0.69l-1.1,0.09l-1.31,-1.48l0.53,-0.72l-0.04,-0.84l1.99,-0.86l0.05,-0.59l-0.71,-0.78l0.14,-0.57l1.66,-1.24l1.36,0.77l1.41,1.49l0.06,1.15l0.37,0.38l0.8,0.05l2.06,1.86Z",
            name: "Guyana"
        },
        GE: {
            path: "M521.71,168.93l5.29,0.89l4.07,2.01l1.41,-0.44l2.07,0.56l0.68,1.1l1.07,0.55l-0.12,0.59l0.98,1.29l-1.01,-0.13l-1.81,-0.83l-0.94,0.47l-3.23,0.43l-2.29,-1.39l-2.33,0.05l0.21,-0.97l-0.76,-2.26l-1.45,-1.12l-1.43,-0.39l-0.41,-0.42Z",
            name: "Georgia"
        },
        GB: {
            path: "M412.61,118.72l-2.19,3.22l-0.0,0.45l5.13,-0.3l-0.53,2.37l-2.2,3.12l0.29,0.63l2.37,0.21l2.33,4.3l1.76,0.69l2.2,5.12l2.94,0.77l-0.23,1.62l-1.15,0.88l-0.1,0.52l0.82,1.42l-1.86,1.43l-3.3,-0.02l-4.12,0.87l-1.04,-0.58l-0.47,0.06l-1.51,1.41l-2.12,-0.34l-1.86,1.18l-0.6,-0.29l3.19,-3.0l2.16,-0.69l0.28,-0.41l-0.34,-0.36l-3.73,-0.53l-0.4,-0.76l2.2,-0.87l0.17,-0.61l-1.26,-1.67l0.36,-1.7l3.38,0.28l0.43,-0.33l0.37,-1.99l-1.79,-2.49l-3.11,-0.72l-0.38,-0.59l0.79,-1.35l-0.04,-0.46l-0.82,-0.97l-0.61,0.01l-0.68,0.84l-0.1,-2.34l-1.23,-1.88l0.85,-3.47l1.77,-2.68l1.85,0.26l2.17,-0.22ZM406.26,132.86l-1.01,1.77l-1.57,-0.59l-1.16,0.01l0.37,-1.54l-0.39,-1.39l1.45,-0.1l2.3,1.84Z",
            name: "United Kingdom"
        },
        GA: {
            path: "M453.24,279.52l-0.08,0.98l0.7,1.29l2.36,0.24l-0.98,2.63l1.18,1.79l0.25,1.78l-0.29,1.52l-0.6,0.93l-1.84,-0.09l-1.23,-1.11l-0.66,0.23l-0.15,0.84l-1.42,0.26l-1.02,0.7l-0.11,0.52l0.77,1.35l-1.34,0.97l-3.94,-4.3l-1.44,-2.45l0.06,-0.6l0.54,-0.81l1.05,-3.46l4.17,-0.07l0.4,-0.4l-0.02,-2.66l2.39,0.21l1.25,-0.27Z",
            name: "Gabon"
        },
        GN: {
            path: "M391.8,254.11l0.47,0.8l1.11,-0.32l0.98,0.7l1.07,0.2l2.26,-1.22l0.64,0.44l1.13,1.56l-0.48,1.4l0.8,0.3l-0.08,0.48l0.46,0.68l-0.35,1.36l1.05,2.61l-1.0,0.69l0.03,1.41l-0.72,-0.06l-1.08,1.0l-0.24,-0.27l0.07,-1.11l-1.05,-1.54l-1.79,0.21l-0.35,-2.01l-1.6,-2.18l-2.0,-0.0l-1.31,0.54l-1.95,2.18l-1.86,-2.19l-1.2,-0.78l-0.3,-1.11l-0.8,-0.85l0.65,-0.72l0.81,-0.03l1.64,-0.8l0.23,-1.87l2.67,0.64l0.89,-0.3l1.21,0.15Z",
            name: "Guinea"
        },
        GM: {
            path: "M379.31,251.39l0.1,-0.35l2.43,-0.07l0.74,-0.61l0.51,-0.03l0.77,0.49l-1.03,-0.3l-1.87,0.9l-1.65,-0.04ZM384.03,250.91l0.91,0.05l0.75,-0.24l-0.59,0.31l-1.08,-0.13Z",
            name: "Gambia"
        },
        GL: {
            path: "M353.02,1.2l14.69,4.67l-3.68,1.89l-22.97,0.86l-0.36,0.27l0.12,0.43l1.55,1.18l8.79,-0.66l7.48,2.07l4.86,-1.77l1.66,1.73l-2.53,3.19l-0.01,0.48l0.46,0.15l6.35,-2.2l12.06,-2.31l7.24,1.13l1.09,1.99l-9.79,4.01l-1.44,1.32l-7.87,0.98l-0.35,0.41l0.38,0.38l5.07,0.24l-2.53,3.58l-2.07,3.81l0.08,6.05l2.57,3.11l-3.22,0.2l-4.12,1.66l-0.05,0.72l4.45,2.65l0.51,3.75l-2.3,0.4l-0.25,0.64l2.79,3.69l-4.82,0.31l-0.36,0.29l0.16,0.44l2.62,1.8l-0.59,1.22l-3.3,0.7l-3.45,0.01l-0.29,0.68l3.03,3.12l0.02,1.34l-4.4,-1.73l-1.72,1.35l0.15,0.66l3.31,1.15l3.13,2.71l0.81,3.16l-3.85,0.75l-4.89,-4.26l-0.47,-0.03l-0.17,0.44l0.79,2.86l-2.71,2.21l-0.13,0.44l0.37,0.27l8.73,0.34l-12.32,6.64l-7.24,1.48l-2.94,0.08l-2.69,1.75l-3.43,4.41l-5.24,2.84l-1.73,0.18l-7.12,2.1l-2.15,2.52l-0.13,2.99l-1.19,2.45l-4.01,3.09l-0.14,0.44l0.97,2.9l-2.28,6.48l-3.1,0.2l-3.83,-3.07l-4.86,-0.02l-2.25,-1.93l-1.7,-3.79l-4.3,-4.84l-1.21,-2.49l-0.44,-3.8l-3.32,-3.63l0.84,-2.86l-1.56,-1.7l2.28,-4.6l3.83,-1.74l1.03,-1.96l0.52,-3.47l-0.59,-0.41l-4.17,2.21l-2.07,0.58l-2.72,-1.28l-0.15,-2.71l0.85,-2.09l2.01,-0.06l5.06,1.2l0.46,-0.23l-0.14,-0.49l-6.54,-4.47l-2.67,0.55l-1.58,-0.86l2.56,-4.01l-0.03,-0.48l-1.5,-1.74l-4.98,-8.5l-3.13,-1.96l0.03,-1.88l-0.24,-0.37l-6.85,-3.02l-5.36,-0.38l-12.7,0.58l-2.78,-1.57l-3.66,-2.77l5.73,-1.45l5.0,-0.28l0.38,-0.38l-0.35,-0.41l-10.67,-1.38l-5.3,-2.06l0.25,-1.54l18.41,-5.26l1.22,-2.27l-0.25,-0.55l-6.14,-1.86l1.68,-1.77l8.55,-4.03l3.59,-0.63l0.3,-0.54l-0.88,-2.27l5.47,-1.47l7.65,-0.95l7.55,-0.05l3.04,1.85l6.48,-3.27l5.81,2.22l3.56,0.5l5.16,1.94l0.5,-0.21l-0.17,-0.52l-5.71,-3.13l0.28,-2.13l8.12,-3.6l8.7,0.28l3.35,-2.34l8.71,-0.6l19.93,0.8Z",
            name: "Greenland"
        },
        KW: {
            path: "M540.81,207.91l0.37,0.86l-0.17,0.76l0.6,1.53l-0.95,0.04l-0.82,-1.28l-1.57,-0.18l1.31,-1.88l1.22,0.17Z",
            name: "Kuwait"
        },
        GH: {
            path: "M420.53,257.51l-0.01,0.72l0.96,1.2l0.24,3.73l0.59,0.95l-0.51,2.1l0.19,1.41l1.02,2.21l-6.97,2.84l-1.8,-0.57l0.04,-0.89l-1.02,-2.04l0.61,-2.65l1.07,-2.32l-0.96,-6.47l5.01,0.07l0.94,-0.39l0.61,0.11Z",
            name: "Ghana"
        },
        OM: {
            path: "M568.09,230.93l-0.91,1.67l-1.22,0.04l-0.6,0.76l-0.41,1.51l0.27,1.58l-1.16,0.05l-1.56,0.97l-0.76,1.74l-1.62,0.05l-0.98,0.65l-0.17,1.15l-0.89,0.52l-1.49,-0.18l-2.4,0.94l-2.47,-5.4l7.35,-2.71l1.67,-5.23l-1.12,-2.09l0.05,-0.83l0.67,-1.0l0.07,-1.05l0.9,-0.42l-0.05,-2.07l0.7,-0.01l1.0,1.62l1.51,1.08l3.3,0.84l1.73,2.29l0.81,0.37l-1.23,2.35l-0.99,0.79Z",
            name: "Oman"
        },
        _1: {
            path: "M531.15,258.94l1.51,0.12l5.13,-0.95l5.3,-1.48l-0.01,4.4l-2.67,3.39l-1.85,0.01l-8.04,-2.94l-2.55,-3.17l1.12,-1.71l2.04,2.34Z",
            name: "Somaliland"
        },
        _0: {
            path: "M472.77,172.64l-1.08,-1.29l0.96,-0.77l0.29,-0.83l1.98,1.64l-0.36,0.67l-1.79,0.58Z",
            name: "Kosovo"
        },
        JO: {
            path: "M518.64,201.38l-5.14,1.56l-0.19,0.65l2.16,2.39l-0.89,1.14l-1.71,0.34l-1.71,1.8l-2.34,-0.37l1.21,-4.32l0.56,-4.07l2.8,0.94l4.46,-2.71l0.79,2.66Z",
            name: "Jordan"
        },
        HR: {
            path: "M455.59,162.84l1.09,0.07l-0.82,0.94l-0.27,-1.01ZM456.96,162.92l0.62,-0.41l1.73,0.45l0.42,-0.4l-0.01,-0.59l0.86,-0.52l0.2,-1.05l1.63,-0.68l2.57,1.68l2.07,0.6l0.87,-0.31l1.05,1.57l-0.52,0.63l-1.05,-0.56l-1.68,0.04l-2.1,-0.5l-1.29,0.06l-0.57,0.49l-0.59,-0.47l-0.62,0.16l-0.46,1.7l1.79,2.42l2.79,2.75l-1.18,-0.87l-2.21,-0.87l-1.67,-1.78l0.13,-0.63l-1.05,-1.19l-0.32,-1.27l-1.42,-0.43Z",
            name: "Croatia"
        },
        HT: {
            path: "M237.05,238.38l-1.16,0.43l-0.91,-0.55l0.05,-0.2l2.02,0.31ZM237.53,238.43l1.06,0.12l-0.05,0.01l-1.01,-0.12ZM239.25,238.45l0.79,-0.51l0.06,-0.62l-1.02,-1.0l0.02,-0.82l-0.3,-0.4l-0.93,-0.32l3.16,0.45l0.02,1.84l-0.48,0.34l-0.08,0.58l0.54,0.72l-1.78,-0.26Z",
            name: "Haiti"
        },
        HU: {
            path: "M462.08,157.89l0.65,-1.59l-0.09,-0.44l0.64,-0.0l0.39,-0.34l0.1,-0.69l1.75,0.87l2.32,-0.37l0.43,-0.66l3.49,-0.78l0.69,-0.78l0.57,-0.14l2.57,0.93l0.67,-0.23l1.03,0.65l0.08,0.37l-1.42,0.71l-2.59,4.14l-1.8,0.53l-1.68,-0.1l-2.74,1.23l-1.85,-0.54l-2.54,-1.66l-0.66,-1.1Z",
            name: "Hungary"
        },
        HN: {
            path: "M199.6,249.52l-1.7,-1.21l0.06,-0.94l3.04,-2.14l2.37,0.28l1.27,-0.09l1.1,-0.52l1.3,0.28l1.14,-0.25l1.38,0.37l2.23,1.37l-2.36,0.93l-1.23,-0.39l-0.88,1.3l-1.28,0.99l-0.98,-0.22l-0.42,0.52l-0.96,0.05l-0.36,0.41l0.04,0.88l-0.52,0.6l-0.3,0.04l-0.3,-0.55l-0.66,-0.31l0.11,-0.67l-0.48,-0.65l-0.87,-0.26l-0.73,0.2Z",
            name: "Honduras"
        },
        PR: {
            path: "M256.17,238.73l-0.26,0.27l-2.83,0.05l-0.07,-0.55l1.95,-0.1l1.22,0.33Z",
            name: "Puerto Rico"
        },
        PS: {
            path: "M509.21,203.07l0.1,-0.06l-0.02,0.03l-0.09,0.03ZM509.36,202.91l-0.02,-0.63l-0.33,-0.16l0.31,-1.09l0.24,0.1l-0.2,1.78Z",
            name: "Palestine"
        },
        PT: {
            path: "M401.84,187.38l-0.64,0.47l-1.13,-0.35l-0.91,0.17l0.28,-1.78l-0.24,-1.78l-1.25,-0.56l-0.45,-0.84l0.17,-1.66l1.01,-1.18l0.69,-2.92l-0.04,-1.39l-0.59,-1.9l1.3,-0.85l0.84,1.35l3.1,-0.3l0.46,0.99l-1.05,0.94l-0.03,2.16l-0.41,0.57l-0.08,1.1l-0.79,0.18l-0.26,0.59l0.91,1.6l-0.63,1.75l0.76,1.09l-1.1,1.52l0.07,1.05Z",
            name: "Portugal"
        },
        PY: {
            path: "M274.9,336.12l0.74,1.52l-0.16,3.45l0.32,0.41l2.64,0.5l1.11,-0.47l1.4,0.59l0.36,0.6l0.53,3.42l1.27,0.4l0.98,-0.38l0.51,0.27l-0.0,1.18l-1.21,5.32l-2.09,1.9l-1.8,0.4l-4.71,-0.98l2.2,-3.63l-0.32,-1.5l-2.78,-1.28l-3.03,-1.94l-2.07,-0.44l-4.34,-4.06l0.91,-2.9l0.08,-1.42l1.07,-2.04l4.13,-0.72l2.18,0.03l2.05,1.17l0.03,0.59Z",
            name: "Paraguay"
        },
        PA: {
            path: "M213.8,263.68l0.26,-1.52l-0.36,-0.26l-0.01,-0.49l0.44,-0.1l0.93,1.4l1.26,0.03l0.77,0.49l1.38,-0.23l2.51,-1.11l0.86,-0.72l3.45,0.85l1.4,1.18l0.41,1.74l-0.21,0.34l-0.53,-0.12l-0.47,0.29l-0.16,0.6l-0.68,-1.28l0.45,-0.49l-0.19,-0.66l-0.47,-0.13l-0.54,-0.84l-1.5,-0.75l-1.1,0.16l-0.75,0.99l-1.62,0.84l-0.18,0.96l0.85,0.97l-0.58,0.45l-0.69,0.08l-0.34,-1.18l-1.27,0.03l-0.71,-1.05l-2.59,-0.46Z",
            name: "Panama"
        },
        PG: {
            path: "M808.58,298.86l2.54,2.56l-0.13,0.26l-0.33,0.12l-0.87,-0.78l-1.22,-2.16ZM801.41,293.04l0.5,0.29l0.26,0.27l-0.49,-0.35l-0.27,-0.21ZM803.17,294.58l0.59,0.5l0.08,1.06l-0.29,-0.91l-0.38,-0.65ZM796.68,298.41l0.52,0.75l1.43,-0.19l2.27,-1.81l-0.01,-1.43l1.12,0.16l-0.04,1.1l-0.7,1.28l-1.12,0.18l-0.62,0.79l-2.46,1.11l-1.17,-0.0l-3.08,-1.25l3.41,0.0l0.45,-0.68ZM789.15,303.55l2.31,1.8l1.59,2.61l1.34,0.13l-0.06,0.66l0.31,0.43l1.06,0.24l0.06,0.65l2.25,1.05l-1.22,0.13l-0.72,-0.63l-4.56,-0.65l-3.22,-2.87l-1.49,-2.34l-3.27,-1.1l-2.38,0.72l-1.59,0.86l-0.2,0.42l0.27,1.55l-1.55,0.68l-1.36,-0.4l-2.21,-0.09l-0.08,-15.41l8.39,2.93l2.95,2.4l0.6,1.64l4.02,1.49l0.31,0.68l-1.76,0.21l-0.33,0.52l0.55,1.68Z",
            name: "Papua New Guinea"
        },
        PE: {
            path: "M244.96,295.21l-1.26,-0.07l-0.57,0.42l-1.93,0.45l-2.98,1.75l-0.36,1.36l-0.58,0.8l0.12,1.37l-1.24,0.59l-0.22,1.22l-0.62,0.84l1.04,2.27l1.28,1.44l-0.41,0.84l0.32,0.57l1.48,0.13l1.16,1.37l2.21,0.07l1.63,-1.08l-0.13,3.02l0.3,0.4l1.14,0.29l1.31,-0.34l1.9,3.59l-0.48,0.85l-0.17,3.85l-0.94,1.59l0.35,0.75l-0.47,1.07l0.98,1.97l-2.1,3.82l-0.98,0.5l-2.17,-1.28l-0.39,-1.16l-4.95,-2.58l-4.46,-2.79l-1.84,-1.51l-0.91,-1.84l0.3,-0.96l-2.11,-3.33l-4.82,-9.68l-1.04,-1.2l-0.87,-1.94l-3.4,-2.48l0.58,-1.18l-1.13,-2.23l0.66,-1.49l1.45,-1.15l-0.6,0.98l0.07,0.92l0.47,0.36l1.74,0.03l0.97,1.17l0.54,0.07l1.42,-1.03l0.6,-1.84l1.42,-2.02l3.04,-1.04l2.73,-2.62l0.86,-1.74l-0.1,-1.87l1.44,1.02l0.9,1.25l1.06,0.59l1.7,2.73l1.86,0.31l1.45,-0.61l0.96,0.39l1.36,-0.19l1.45,0.89l-1.4,2.21l0.31,0.61l0.59,0.05l0.47,0.5Z",
            name: "Peru"
        },
        PK: {
            path: "M615.09,192.34l-1.83,1.81l-2.6,0.39l-3.73,-0.68l-1.58,1.33l-0.09,0.42l1.77,4.39l1.7,1.23l-1.69,1.27l-0.12,2.14l-2.33,2.64l-1.6,2.8l-2.46,2.67l-3.03,-0.07l-2.76,2.83l0.05,0.6l1.5,1.11l0.26,1.9l1.44,1.5l0.37,1.68l-5.01,-0.01l-1.78,1.7l-1.42,-0.52l-0.76,-1.87l-2.27,-2.15l-11.61,0.86l0.71,-2.34l3.43,-1.32l0.25,-0.44l-0.21,-1.24l-1.2,-0.65l-0.28,-2.46l-2.29,-1.14l-1.28,-1.94l2.82,0.94l2.62,-0.38l1.42,0.33l0.76,-0.56l1.71,0.19l3.25,-1.14l0.27,-0.36l0.08,-2.19l1.18,-1.32l1.68,0.0l0.58,-0.82l1.6,-0.3l1.19,0.16l0.98,-0.78l0.02,-1.88l0.93,-1.47l1.48,-0.66l0.19,-0.55l-0.66,-1.25l2.04,-0.11l0.69,-1.01l-0.02,-1.16l1.11,-1.06l-0.17,-1.78l-0.49,-1.03l1.15,-0.98l5.42,-0.91l2.6,-0.82l1.6,1.16l0.97,2.34l3.45,0.97Z",
            name: "Pakistan"
        },
        PH: {
            path: "M737.01,263.84l0.39,2.97l-0.44,1.18l-0.55,-1.53l-0.67,-0.14l-1.17,1.28l0.65,2.09l-0.42,0.69l-2.48,-1.23l-0.57,-1.49l0.65,-1.03l-0.1,-0.54l-1.59,-1.19l-0.56,0.08l-0.65,0.87l-1.23,0.0l-1.58,0.97l0.83,-1.8l2.56,-1.42l0.65,0.84l0.45,0.13l1.9,-0.69l0.56,-1.11l1.5,-0.06l0.38,-0.43l-0.09,-1.19l1.21,0.71l0.36,2.02ZM733.59,256.58l0.05,0.75l0.08,0.26l-0.8,-0.42l-0.18,-0.71l0.85,0.12ZM734.08,256.1l-0.12,-1.12l-1.0,-1.27l1.36,0.03l0.53,0.73l0.51,2.04l-1.27,-0.4ZM733.76,257.68l0.38,0.98l-0.32,0.15l-0.07,-1.13ZM724.65,238.43l1.46,0.7l0.72,-0.31l-0.32,1.17l0.79,1.71l-0.57,1.84l-1.53,1.04l-0.39,2.25l0.56,2.04l1.63,0.57l1.16,-0.27l2.71,1.23l-0.19,1.08l0.76,0.84l-0.08,0.36l-1.4,-0.9l-0.88,-1.27l-0.66,0.0l-0.38,0.55l-1.6,-1.31l-2.15,0.36l-0.87,-0.39l0.07,-0.61l0.66,-0.55l-0.01,-0.62l-0.75,-0.59l-0.72,0.44l-0.74,-0.87l-0.39,-2.49l0.32,0.27l0.66,-0.28l0.26,-3.97l0.7,-2.02l1.14,0.0ZM731.03,258.87l-0.88,0.85l-1.19,1.94l-1.05,-1.19l0.93,-1.1l0.32,-1.47l0.52,-0.06l-0.27,1.15l0.22,0.45l0.49,-0.12l1.0,-1.32l-0.08,0.85ZM726.83,255.78l0.83,0.38l1.17,-0.0l-0.02,0.48l-2.0,1.4l0.03,-2.26ZM724.81,252.09l-0.38,1.27l-1.42,-1.95l1.2,0.05l0.6,0.63ZM716.55,261.82l1.1,-0.95l0.03,-0.03l-0.28,0.36l-0.85,0.61ZM719.22,259.06l0.04,-0.06l0.8,-1.53l0.16,0.75l-1.0,0.84Z",
            name: "Philippines"
        },
        PL: {
            path: "M468.44,149.42l-1.11,-1.54l-1.86,-0.33l-0.48,-1.05l-1.72,-0.37l-0.65,0.69l-0.72,-0.36l0.11,-0.61l-0.33,-0.46l-1.75,-0.27l-1.04,-0.93l-0.94,-1.94l0.16,-1.22l-0.62,-1.8l-0.78,-1.07l0.57,-1.04l-0.48,-1.43l1.41,-0.83l6.91,-2.71l2.14,0.5l0.52,0.91l5.51,0.44l4.55,-0.05l1.07,0.31l0.48,0.84l0.15,1.58l0.65,1.2l-0.01,0.99l-1.27,0.58l-0.19,0.54l0.73,1.48l0.08,1.55l1.2,2.76l-0.17,0.58l-1.23,0.44l-2.27,2.72l0.18,0.95l-1.97,-1.03l-1.98,0.4l-1.36,-0.28l-1.24,0.58l-1.07,-0.97l-1.16,0.24Z",
            name: "Poland"
        },
        "-99": {
            path: "M504.91,192.87l0.34,0.01l0.27,-0.07l-0.29,0.26l-0.31,-0.2Z",
            name: "N. Cyprus"
        },
        ZM: {
            path: "M481.47,313.3l0.39,0.31l2.52,0.14l0.99,1.17l2.01,0.35l1.4,-0.64l0.69,1.17l1.78,0.33l1.84,2.35l2.23,0.18l0.4,-0.43l-0.21,-2.74l-0.62,-0.3l-0.48,0.32l-1.98,-1.17l0.72,-5.29l-0.51,-1.18l0.57,-1.3l3.68,-0.62l0.26,0.63l1.21,0.63l0.9,-0.22l2.16,0.67l1.33,0.71l1.07,1.02l0.56,1.87l-0.88,2.7l0.43,2.09l-0.73,0.87l-0.76,2.37l0.59,0.68l-6.6,1.83l-0.29,0.44l0.19,1.45l-1.68,0.35l-1.43,1.02l-0.38,0.87l-0.87,0.26l-3.48,3.69l-4.16,-0.53l-1.52,-1.0l-1.77,-0.13l-1.83,0.52l-3.04,-3.4l0.11,-7.59l4.82,0.03l0.39,-0.49l-0.18,-0.76l0.33,-0.83l-0.4,-1.36l0.24,-1.05Z",
            name: "Zambia"
        },
        EH: {
            path: "M384.42,230.28l0.25,-0.79l1.06,-1.29l0.8,-3.51l3.38,-2.78l0.7,-1.81l0.06,4.84l-1.98,0.2l-0.94,1.59l0.39,3.56l-3.7,-0.01ZM392.01,218.1l0.7,-1.8l1.77,-0.24l2.09,0.34l0.95,-0.62l1.28,-0.07l-0.0,2.51l-6.79,-0.12Z",
            name: "W. Sahara"
        },
        EE: {
            path: "M485.71,115.04l2.64,0.6l2.56,0.11l-1.6,1.91l0.61,3.54l-0.81,0.87l-1.78,-0.01l-3.22,-1.76l-1.8,0.45l0.21,-1.53l-0.58,-0.41l-0.69,0.34l-1.26,-1.03l-0.17,-1.63l2.83,-0.92l3.05,-0.52Z",
            name: "Estonia"
        },
        EG: {
            path: "M492.06,205.03l1.46,0.42l2.95,-1.64l2.04,-0.21l1.53,0.3l0.59,1.19l0.69,0.04l0.41,-0.64l1.81,0.58l1.95,0.16l1.04,-0.51l1.42,4.08l-2.03,4.54l-1.66,-1.77l-1.76,-3.85l-0.64,-0.12l-0.36,0.67l1.04,2.88l3.44,6.95l1.78,3.04l2.03,2.65l-0.36,0.53l0.23,2.01l2.7,2.19l-28.41,0.0l0.0,-18.96l-0.73,-2.2l0.59,-1.56l-0.32,-1.26l0.68,-0.99l3.06,-0.04l4.82,1.52Z",
            name: "Egypt"
        },
        ZA: {
            path: "M467.14,373.21l-0.13,-1.96l-0.68,-1.56l0.7,-0.68l-0.13,-2.33l-4.56,-8.19l0.77,-0.86l0.6,0.45l0.69,1.31l2.83,0.72l1.5,-0.26l2.24,-1.39l0.19,-9.55l1.35,2.3l-0.21,1.5l0.61,1.2l0.4,0.19l1.79,-0.27l2.6,-2.07l0.69,-1.32l0.96,-0.48l2.19,1.04l2.04,0.13l1.77,-0.65l0.85,-2.12l1.38,-0.33l1.59,-2.76l2.15,-1.89l3.41,-1.87l2.0,0.45l1.02,-0.28l0.99,0.2l1.75,5.29l-0.38,3.25l-0.81,-0.23l-1.0,0.46l-0.87,1.68l-0.05,1.16l1.97,1.84l1.47,-0.29l0.69,-1.18l1.09,0.01l-0.76,3.69l-0.58,1.09l-2.2,1.79l-3.17,4.76l-2.8,2.83l-3.57,2.88l-2.53,1.05l-1.22,0.14l-0.51,0.7l-1.18,-0.32l-1.39,0.5l-2.59,-0.52l-1.61,0.33l-1.18,-0.11l-2.55,1.1l-2.1,0.44l-1.6,1.07l-0.85,0.05l-0.93,-0.89l-0.93,-0.15l-0.97,-1.13l-0.25,0.05ZM491.45,364.19l0.62,-0.93l1.48,-0.59l1.18,-2.19l-0.07,-0.49l-1.99,-1.69l-1.66,0.56l-1.43,1.14l-1.34,1.73l0.02,0.51l1.88,2.11l1.31,-0.16Z",
            name: "South Africa"
        },
        EC: {
            path: "M231.86,285.53l0.29,1.59l-0.69,1.45l-2.61,2.51l-3.13,1.11l-1.53,2.18l-0.49,1.68l-1.0,0.73l-1.02,-1.11l-1.78,-0.16l0.67,-1.15l-0.24,-0.86l1.25,-2.13l-0.54,-1.09l-0.67,-0.08l-0.72,0.87l-0.87,-0.64l0.35,-0.69l-0.36,-1.96l0.81,-0.51l0.45,-1.51l0.92,-1.57l-0.07,-0.97l2.65,-1.33l2.75,1.35l0.77,1.05l2.12,0.35l0.76,-0.32l1.96,1.21Z",
            name: "Ecuador"
        },
        AL: {
            path: "M470.32,171.8l0.74,0.03l0.92,0.89l-0.17,1.95l0.36,1.28l1.01,0.82l-1.82,2.83l-0.19,-0.61l-1.25,-0.89l-0.18,-1.2l0.53,-2.82l-0.54,-1.47l0.6,-0.83Z",
            name: "Albania"
        },
        AO: {
            path: "M461.55,300.03l1.26,3.15l1.94,2.36l2.47,-0.53l1.25,0.32l0.44,-0.18l0.93,-1.92l1.31,-0.08l0.41,-0.44l0.47,-0.0l-0.1,0.41l0.39,0.49l2.65,-0.02l0.03,1.19l0.48,1.01l-0.34,1.52l0.18,1.55l0.83,1.04l-0.13,2.85l0.54,0.39l3.96,-0.41l-0.1,1.79l0.39,1.05l-0.24,1.43l-4.7,-0.03l-0.4,0.39l-0.12,8.13l2.92,3.49l-3.83,0.88l-5.89,-0.36l-1.88,-1.24l-10.47,0.22l-1.3,-1.01l-1.85,-0.16l-2.4,0.77l-0.15,-1.06l0.33,-2.16l1.0,-3.45l1.35,-3.2l2.24,-2.8l0.33,-2.06l-0.13,-1.53l-0.8,-1.08l-1.21,-2.87l0.87,-1.62l-1.27,-4.12l-1.17,-1.53l2.47,-0.63l7.03,0.03ZM451.71,298.87l-0.47,-1.25l1.25,-1.11l0.32,0.3l-0.99,1.03l-0.12,1.03Z",
            name: "Angola"
        },
        KZ: {
            path: "M552.8,172.89l0.46,-1.27l-0.48,-1.05l-2.96,-1.19l-1.06,-2.58l-1.37,-0.87l-0.03,-0.3l1.95,0.23l0.45,-0.38l0.08,-1.96l1.75,-0.41l2.1,0.45l0.48,-0.33l0.45,-3.04l-0.45,-2.09l-0.41,-0.31l-2.42,0.15l-2.36,-0.73l-2.87,1.37l-2.17,0.61l-0.85,-0.34l0.13,-1.61l-1.6,-2.12l-2.02,-0.08l-1.78,-1.82l1.29,-2.18l-0.57,-0.95l1.62,-2.91l2.21,1.63l0.63,-0.27l0.29,-2.22l4.92,-3.43l3.71,-0.08l8.4,3.6l2.92,-1.36l3.77,-0.06l3.11,1.66l0.51,-0.11l0.6,-0.81l3.31,0.13l0.39,-0.25l0.63,-1.57l-0.17,-0.5l-3.5,-1.98l1.87,-1.27l-0.13,-1.03l1.98,-0.72l0.18,-0.62l-1.59,-2.06l0.81,-0.82l9.23,-1.18l1.33,-0.88l6.18,-1.26l2.26,-1.42l4.08,0.68l0.73,3.33l0.51,0.3l2.48,-0.8l2.79,1.02l-0.17,1.56l0.43,0.44l2.55,-0.24l4.89,-2.53l0.03,0.32l3.15,2.61l5.56,8.47l0.65,0.02l1.12,-1.46l3.15,1.74l3.76,-0.78l1.15,0.49l1.14,1.8l1.84,0.76l0.99,1.29l3.35,-0.25l1.02,1.52l-1.6,1.81l-1.93,0.28l-0.34,0.38l-0.11,3.05l-1.13,1.16l-4.75,-1.0l-0.46,0.27l-1.76,5.47l-1.1,0.59l-4.91,1.23l-0.27,0.54l2.1,4.97l-1.37,0.63l-0.23,0.41l0.13,1.13l-0.88,-0.25l-1.42,-1.13l-7.89,-0.4l-0.92,0.31l-3.73,-1.22l-1.42,0.63l-0.53,1.66l-3.72,-0.94l-1.85,0.43l-0.76,1.4l-4.65,2.62l-1.13,2.08l-0.44,0.01l-0.92,-1.4l-2.87,-0.09l-0.45,-2.14l-0.38,-0.32l-0.8,-0.01l0.0,-2.96l-3.0,-2.22l-7.31,0.58l-2.35,-2.68l-6.71,-3.69l-6.45,1.83l-0.29,0.39l0.1,10.85l-0.7,0.08l-1.62,-2.17l-1.83,-0.96l-3.11,0.59l-0.64,0.51Z",
            name: "Kazakhstan"
        },
        ET: {
            path: "M516.04,247.79l1.1,0.84l1.63,-0.45l0.68,0.47l1.63,0.03l2.01,0.94l1.73,1.66l1.64,2.07l-1.52,2.04l0.16,1.72l0.39,0.38l2.05,0.0l-0.36,1.03l2.86,3.58l8.32,3.08l1.31,0.02l-6.32,6.75l-3.1,0.11l-2.36,1.77l-1.47,0.04l-0.86,0.79l-1.38,-0.0l-1.32,-0.81l-2.29,1.05l-0.76,0.98l-3.29,-0.41l-3.07,-2.07l-1.8,-0.07l-0.62,-0.6l0.0,-1.24l-0.28,-0.38l-1.15,-0.37l-1.4,-2.59l-1.19,-0.68l-0.47,-1.0l-1.27,-1.23l-1.16,-0.22l0.43,-0.72l1.45,-0.28l0.41,-0.95l-0.03,-2.21l0.68,-2.44l1.05,-0.63l1.43,-3.06l1.57,-1.37l1.02,-2.51l0.35,-1.88l2.52,0.46l0.44,-0.24l0.58,-1.43Z",
            name: "Ethiopia"
        },
        ZW: {
            path: "M498.91,341.09l-1.11,-0.22l-0.92,0.28l-2.09,-0.44l-1.5,-1.11l-1.89,-0.43l-0.62,-1.4l-0.01,-0.84l-0.3,-0.38l-0.97,-0.25l-2.71,-2.74l-1.92,-3.32l3.83,0.45l3.73,-3.82l1.08,-0.44l0.26,-0.77l1.25,-0.9l1.41,-0.26l0.5,0.89l1.99,-0.05l1.72,1.17l1.11,0.17l1.05,0.66l0.01,2.99l-0.59,3.76l0.38,0.86l-0.23,1.23l-0.39,0.35l-0.63,1.81l-2.43,2.75Z",
            name: "Zimbabwe"
        },
        ES: {
            path: "M416.0,169.21l1.07,1.17l4.61,1.38l1.06,-0.57l2.6,1.26l2.71,-0.3l0.09,1.12l-2.14,1.8l-3.11,0.61l-0.31,0.31l-0.2,0.89l-1.54,1.69l-0.97,2.4l0.84,1.74l-1.32,1.27l-0.48,1.68l-1.88,0.65l-1.66,2.07l-5.36,-0.01l-1.79,1.08l-0.89,0.98l-0.88,-0.17l-0.79,-0.82l-0.68,-1.59l-2.37,-0.63l-0.11,-0.5l1.21,-1.82l-0.77,-1.13l0.61,-1.68l-0.76,-1.62l0.87,-0.49l0.09,-1.25l0.42,-0.6l0.03,-2.11l0.99,-0.69l0.13,-0.5l-1.03,-1.73l-1.46,-0.11l-0.61,0.38l-1.06,0.0l-0.52,-1.23l-0.53,-0.21l-1.32,0.67l-0.01,-1.49l-0.75,-0.96l3.03,-1.88l2.99,0.53l3.32,-0.02l2.63,0.51l6.01,-0.06Z",
            name: "Spain"
        },
        ER: {
            path: "M520.38,246.23l3.42,2.43l3.5,3.77l0.84,0.54l-0.95,-0.01l-3.51,-3.89l-2.33,-1.15l-1.73,-0.07l-0.91,-0.51l-1.26,0.51l-1.34,-1.02l-0.61,0.17l-0.66,1.61l-2.35,-0.43l-0.17,-0.67l1.29,-5.29l0.61,-0.61l1.95,-0.53l0.87,-1.01l1.17,2.41l0.68,2.33l1.49,1.43Z",
            name: "Eritrea"
        },
        ME: {
            path: "M468.91,172.53l-1.22,-1.02l0.47,-1.81l0.89,-0.72l2.26,1.51l-0.5,0.57l-0.75,-0.27l-1.14,1.73Z",
            name: "Montenegro"
        },
        MD: {
            path: "M488.41,153.73l1.4,-0.27l1.72,0.93l1.07,0.15l0.85,0.65l-0.14,0.84l0.96,0.85l1.12,2.47l-1.15,-0.07l-0.66,-0.41l-0.52,0.25l-0.09,0.86l-1.08,1.89l-0.27,-0.86l0.25,-1.34l-0.16,-1.6l-3.29,-4.34Z",
            name: "Moldova"
        },
        MG: {
            path: "M545.91,319.14l0.4,3.03l0.62,1.21l-0.21,1.02l-0.57,-0.8l-0.69,-0.01l-0.47,0.76l0.41,2.12l-0.18,0.87l-0.73,0.78l-0.15,2.14l-4.71,15.2l-1.06,2.88l-3.92,1.64l-3.12,-1.49l-0.6,-1.21l-0.19,-2.4l-0.86,-2.05l-0.21,-1.77l0.38,-1.62l1.21,-0.75l0.01,-0.76l1.19,-2.04l0.23,-1.66l-1.06,-2.99l-0.19,-2.21l0.81,-1.33l0.32,-1.46l4.63,-1.22l3.44,-3.0l0.85,-1.4l-0.08,-0.7l0.78,-0.04l1.38,-1.77l0.13,-1.64l0.45,-0.61l1.16,1.69l0.59,1.6Z",
            name: "Madagascar"
        },
        MA: {
            path: "M378.78,230.02l0.06,-0.59l0.92,-0.73l0.82,-1.37l-0.09,-1.04l0.79,-1.7l1.31,-1.58l0.96,-0.59l0.66,-1.55l0.09,-1.47l0.81,-1.48l1.72,-1.07l1.55,-2.69l1.16,-0.96l2.44,-0.39l1.94,-1.82l1.31,-0.78l2.09,-2.28l-0.51,-3.65l1.24,-3.7l1.5,-1.75l4.46,-2.57l2.37,-4.47l1.44,0.01l1.68,1.21l2.32,-0.19l3.47,0.65l0.8,1.54l0.16,1.71l0.86,2.96l0.56,0.59l-0.26,0.61l-3.05,0.44l-1.26,1.05l-1.33,0.22l-0.33,0.37l-0.09,1.78l-2.68,1.0l-1.07,1.42l-4.47,1.13l-4.04,2.01l-0.54,4.64l-1.15,0.06l-0.92,0.61l-1.96,-0.35l-2.42,0.54l-0.74,1.9l-0.86,0.4l-1.14,3.26l-3.53,3.01l-0.8,3.55l-0.96,1.1l-0.29,0.82l-4.95,0.18Z",
            name: "Morocco"
        },
        UZ: {
            path: "M598.64,172.75l-1.63,1.52l0.06,0.64l1.85,1.12l1.97,-0.64l2.21,1.17l-2.52,1.68l-2.59,-0.22l-0.18,-0.41l0.46,-1.23l-0.45,-0.53l-3.35,0.69l-2.1,3.51l-1.87,-0.12l-1.03,1.51l0.22,0.55l1.64,0.62l0.46,1.83l-1.19,2.49l-2.66,-0.53l0.05,-1.36l-0.26,-0.39l-3.3,-1.23l-2.56,-1.4l-4.4,-3.34l-1.34,-3.14l-1.08,-0.6l-2.58,0.13l-0.69,-0.44l-0.47,-2.52l-3.37,-1.6l-0.43,0.05l-2.07,1.72l-2.1,1.01l-0.21,0.47l0.28,1.01l-1.91,0.03l-0.09,-10.5l5.99,-1.7l6.19,3.54l2.71,2.84l7.05,-0.67l2.71,2.01l-0.17,2.81l0.39,0.42l0.9,0.02l0.44,2.14l0.38,0.32l2.94,0.09l0.95,1.42l1.28,-0.24l1.05,-2.04l4.43,-2.5Z",
            name: "Uzbekistan"
        },
        MM: {
            path: "M673.9,230.21l-1.97,1.57l-0.57,0.96l-1.4,0.6l-1.36,1.05l-1.99,0.36l-1.08,2.66l-0.91,0.4l-0.19,0.55l1.21,2.27l2.52,3.43l-0.79,1.91l-0.74,0.41l-0.17,0.52l0.65,1.37l1.61,1.95l0.25,2.58l0.9,2.13l-1.92,3.57l0.68,-2.25l-0.81,-1.74l0.19,-2.65l-1.05,-1.53l-1.24,-6.17l-1.12,-2.26l-0.6,-0.13l-4.34,3.02l-2.39,-0.65l0.77,-2.84l-0.52,-2.61l-1.91,-2.96l0.25,-0.75l-0.29,-0.51l-1.33,-0.3l-1.61,-1.93l-0.1,-1.3l0.82,-0.24l0.04,-1.64l1.02,-0.52l0.21,-0.45l-0.23,-0.95l0.54,-0.96l0.08,-2.22l1.46,0.45l0.47,-0.2l1.12,-2.19l0.16,-1.35l1.33,-2.16l-0.0,-1.52l2.89,-1.66l1.63,0.44l0.5,-0.44l-0.17,-1.4l0.64,-0.36l0.08,-1.04l0.77,-0.11l0.71,1.35l1.06,0.69l-0.03,3.86l-2.38,2.37l-0.3,3.15l0.46,0.43l2.28,-0.38l0.51,2.08l1.47,0.67l-0.6,1.8l0.19,0.48l2.97,1.48l1.64,-0.55l0.02,0.32Z",
            name: "Myanmar"
        },
        ML: {
            path: "M392.61,254.08l-0.19,-2.37l-0.99,-0.87l-0.44,-1.3l-0.09,-1.28l0.81,-0.58l0.35,-1.24l2.37,0.65l1.31,-0.47l0.86,0.15l0.66,-0.56l9.83,-0.04l0.38,-0.28l0.56,-1.8l-0.44,-0.65l-2.35,-21.95l3.27,-0.04l16.7,11.38l0.74,1.31l2.5,1.09l0.02,1.38l0.44,0.39l2.34,-0.21l0.01,5.38l-1.28,1.61l-0.26,1.49l-5.31,0.57l-1.07,0.92l-2.9,0.1l-0.86,-0.48l-1.38,0.36l-2.4,1.08l-0.6,0.87l-1.85,1.09l-0.43,0.7l-0.79,0.39l-1.44,-0.21l-0.81,0.84l-0.34,1.64l-1.91,2.02l-0.06,1.03l-0.67,1.22l0.13,1.16l-0.97,0.39l-0.23,-0.64l-0.52,-0.24l-1.35,0.4l-0.34,0.55l-2.69,-0.28l-0.37,-0.35l-0.02,-0.9l-0.65,-0.35l0.45,-0.64l-0.03,-0.53l-2.12,-2.44l-0.76,-0.01l-2.0,1.16l-0.78,-0.15l-0.8,-0.67l-1.21,0.23Z",
            name: "Mali"
        },
        MN: {
            path: "M676.61,146.48l3.81,1.68l5.67,-1.0l2.37,0.41l2.34,1.5l1.79,1.75l2.29,-0.03l3.12,0.52l2.47,-0.81l3.41,-0.59l3.53,-2.21l1.25,0.29l1.53,1.13l2.27,-0.21l-2.66,5.01l0.64,1.68l0.47,0.21l1.32,-0.38l2.38,0.48l2.02,-1.11l1.76,0.89l2.06,2.02l-0.13,0.53l-1.72,-0.29l-3.77,0.46l-1.88,0.99l-1.76,1.99l-3.71,1.17l-2.45,1.6l-3.83,-0.87l-0.41,0.17l-1.31,1.99l1.04,2.24l-1.52,0.9l-1.74,1.57l-2.79,1.02l-3.78,0.13l-4.05,1.05l-2.77,1.52l-1.16,-0.85l-2.94,0.0l-3.62,-1.79l-2.58,-0.49l-3.4,0.41l-5.12,-0.67l-2.63,0.06l-1.31,-1.6l-1.4,-3.0l-1.48,-0.33l-3.13,-1.94l-6.16,-0.93l-0.71,-1.06l0.86,-3.82l-1.93,-2.71l-3.5,-1.18l-1.95,-1.58l-0.5,-1.72l2.34,-0.52l4.75,-2.8l3.62,-1.47l2.18,0.97l2.46,0.05l1.81,1.53l2.46,0.12l3.95,0.71l2.43,-2.28l0.08,-0.48l-0.9,-1.72l2.24,-2.98l2.62,1.27l4.94,1.17l0.43,2.24Z",
            name: "Mongolia"
        },
        MK: {
            path: "M472.8,173.98l0.49,-0.71l3.57,-0.71l1.0,0.77l0.13,1.45l-0.65,0.53l-1.15,-0.05l-1.12,0.67l-1.39,0.22l-0.79,-0.55l-0.29,-1.03l0.19,-0.6Z",
            name: "Macedonia"
        },
        MW: {
            path: "M505.5,309.31l0.85,1.95l0.15,2.86l-0.69,1.65l0.71,1.8l0.06,1.28l0.49,0.64l0.07,1.06l0.4,0.55l0.8,-0.23l0.55,0.61l0.69,-0.21l0.34,0.6l0.19,2.94l-1.04,0.62l-0.54,1.25l-1.11,-1.08l-0.16,-1.56l0.51,-1.31l-0.32,-1.3l-0.99,-0.65l-0.82,0.12l-2.36,-1.64l0.63,-1.96l0.82,-1.18l-0.46,-2.01l0.9,-2.86l-0.94,-2.51l0.96,0.18l0.29,0.4Z",
            name: "Malawi"
        },
        MR: {
            path: "M407.36,220.66l-2.58,0.03l-0.39,0.44l2.42,22.56l0.36,0.43l-0.39,1.24l-9.75,0.04l-0.56,0.53l-0.91,-0.11l-1.27,0.45l-1.61,-0.66l-0.97,0.03l-0.36,0.29l-0.38,1.35l-0.42,0.23l-2.93,-3.4l-2.96,-1.52l-1.62,-0.03l-1.27,0.54l-1.12,-0.2l-0.65,0.4l-0.08,-0.49l0.68,-1.29l0.31,-2.43l-0.57,-3.91l0.23,-1.21l-0.69,-1.5l-1.15,-1.02l0.25,-0.39l9.58,0.02l0.4,-0.45l-0.46,-3.68l0.47,-1.04l2.12,-0.21l0.36,-0.4l-0.08,-6.4l7.81,0.13l0.41,-0.4l0.01,-3.31l7.76,5.35Z",
            name: "Mauritania"
        },
        UG: {
            path: "M498.55,276.32l0.7,-0.46l1.65,0.5l1.96,-0.57l1.7,0.01l1.45,-0.98l0.91,1.33l1.33,3.95l-2.57,4.03l-1.46,-0.4l-2.54,0.91l-1.37,1.61l-0.01,0.81l-2.42,-0.01l-2.26,1.01l-0.17,-1.59l0.58,-1.04l0.14,-1.94l1.37,-2.28l1.78,-1.58l-0.17,-0.65l-0.72,-0.24l0.13,-2.43Z",
            name: "Uganda"
        },
        MY: {
            path: "M717.47,273.46l-1.39,0.65l-2.12,-0.41l-2.88,-0.0l-0.38,0.28l-0.84,2.75l-0.99,0.96l-1.21,3.29l-1.73,0.45l-2.45,-0.68l-1.39,0.31l-1.33,1.15l-1.59,-0.14l-1.41,0.44l-1.44,-1.19l-0.18,-0.73l1.34,0.53l1.93,-0.47l0.75,-2.22l4.02,-1.03l2.75,-3.21l0.82,0.94l0.64,-0.05l0.4,-0.65l0.96,0.06l0.42,-0.36l0.24,-2.68l1.81,-1.64l1.21,-1.86l0.63,-0.01l1.07,1.05l0.34,1.28l3.44,1.35l-0.06,0.35l-1.37,0.1l-0.35,0.54l0.32,0.88ZM673.68,269.59l0.17,1.09l0.47,0.33l1.65,-0.3l0.87,-0.94l1.61,1.52l0.98,1.56l-0.12,2.81l0.41,2.29l0.95,0.9l0.88,2.44l-1.27,0.12l-5.1,-3.67l-0.34,-1.29l-1.37,-1.59l-0.33,-1.97l-0.88,-1.4l0.25,-1.68l-0.46,-1.05l1.63,0.84Z",
            name: "Malaysia"
        },
        MX: {
            path: "M133.12,200.41l0.2,0.47l9.63,3.33l6.96,-0.02l0.4,-0.4l0.0,-0.74l3.77,0.0l3.55,2.93l1.39,2.83l1.52,1.04l2.08,0.82l0.47,-0.14l1.46,-2.0l1.73,-0.04l1.59,0.98l2.05,3.35l1.47,1.56l1.26,3.14l2.18,1.02l2.26,0.58l-1.18,3.72l-0.42,5.04l1.79,4.89l1.62,1.89l0.61,1.52l1.2,1.42l2.55,0.66l1.37,1.1l7.54,-1.89l1.86,-1.3l1.14,-4.3l4.1,-1.21l3.57,-0.11l0.32,0.3l-0.06,0.94l-1.26,1.45l-0.67,1.71l0.38,0.7l-0.72,2.27l-0.49,-0.3l-1.0,0.08l-1.0,1.39l-0.47,-0.11l-0.53,0.47l-4.26,-0.02l-0.4,0.4l-0.0,1.06l-1.1,0.26l0.1,0.44l1.82,1.44l0.56,0.91l-3.19,0.21l-1.21,2.09l0.24,0.72l-0.2,0.44l-2.24,-2.18l-1.45,-0.93l-2.22,-0.69l-1.52,0.22l-3.07,1.16l-10.55,-3.85l-2.86,-1.96l-3.78,-0.92l-1.08,-1.19l-2.62,-1.43l-1.18,-1.54l-0.38,-0.81l0.66,-0.63l-0.18,-0.53l0.52,-0.76l0.01,-0.91l-2.0,-3.82l-2.21,-2.63l-2.53,-2.09l-1.19,-1.62l-2.2,-1.17l-0.3,-0.43l0.34,-1.48l-0.21,-0.45l-1.23,-0.6l-1.36,-1.2l-0.59,-1.78l-1.54,-0.47l-2.44,-2.55l-0.16,-0.9l-1.33,-2.03l-0.84,-1.99l-0.16,-1.33l-1.81,-1.1l-0.97,0.05l-1.31,-0.7l-0.57,0.22l-0.4,1.12l0.72,3.77l3.51,3.89l0.28,0.78l0.53,0.26l0.41,1.43l1.33,1.73l1.58,1.41l0.8,2.39l1.43,2.41l0.13,1.32l0.37,0.36l1.04,0.08l1.67,2.28l-0.85,0.76l-0.66,-1.51l-1.68,-1.54l-2.91,-1.87l0.06,-1.82l-0.54,-1.68l-2.91,-2.03l-0.55,0.09l-1.95,-1.1l-0.88,-0.94l0.68,-0.08l0.93,-1.01l0.08,-1.78l-1.93,-1.94l-1.46,-0.77l-3.75,-7.56l4.88,-0.42Z",
            name: "Mexico"
        },
        VU: {
            path: "M839.04,322.8l0.22,1.14l-0.44,0.03l-0.2,-1.45l0.42,0.27Z",
            name: "Vanuatu"
        },
        FR: {
            path: "M444.48,172.62l-0.64,1.78l-0.58,-0.31l-0.49,-1.72l0.4,-0.89l1.0,-0.72l0.3,1.85ZM429.64,147.1l1.78,1.58l1.46,-0.13l2.1,1.42l1.35,0.27l1.23,0.83l3.04,0.5l-1.03,1.85l-0.3,2.12l-0.41,0.32l-0.95,-0.24l-0.5,0.43l0.06,0.61l-1.81,1.92l-0.04,1.42l0.55,0.38l0.88,-0.36l0.61,0.97l-0.03,1.0l0.57,0.91l-0.75,1.09l0.65,2.39l1.27,0.57l-0.18,0.82l-2.01,1.53l-4.77,-0.8l-3.82,1.0l-0.53,1.85l-2.49,0.34l-2.71,-1.31l-1.16,0.57l-4.31,-1.29l-0.72,-0.86l1.19,-1.78l0.39,-6.45l-2.58,-3.3l-1.9,-1.66l-3.72,-1.23l-0.19,-1.72l2.81,-0.61l4.12,0.81l0.47,-0.48l-0.6,-2.77l1.94,0.95l5.83,-2.54l0.92,-2.74l1.6,-0.49l0.24,0.78l1.36,0.33l1.05,1.19ZM289.01,278.39l-0.81,0.8l-0.78,0.12l-0.5,-0.66l-0.56,-0.1l-0.91,0.6l-0.46,-0.22l1.09,-2.96l-0.96,-1.77l-0.17,-1.49l1.07,-1.77l2.32,0.75l2.51,2.01l0.3,0.74l-2.14,3.96Z",
            name: "France"
        },
        FI: {
            path: "M492.17,76.39l-0.23,3.5l3.52,2.63l-2.08,2.88l-0.02,0.44l2.8,4.56l-1.59,3.31l2.16,3.24l-0.94,2.39l0.14,0.47l3.44,2.51l-0.77,1.62l-7.52,6.95l-4.5,0.31l-4.38,1.37l-3.8,0.74l-1.44,-1.96l-2.17,-1.11l0.5,-3.66l-1.16,-3.33l1.09,-2.08l2.21,-2.42l5.67,-4.32l1.64,-0.83l0.21,-0.42l-0.46,-2.02l-3.38,-1.89l-0.75,-1.43l-0.22,-6.74l-6.79,-4.8l0.8,-0.62l2.54,2.12l3.46,-0.12l3.0,0.96l2.51,-2.11l1.17,-3.08l3.55,-1.38l2.76,1.53l-0.95,2.79Z",
            name: "Finland"
        },
        FJ: {
            path: "M871.53,326.34l-2.8,1.05l-0.08,-0.23l2.97,-1.21l-0.1,0.39ZM867.58,329.25l0.43,0.37l-0.27,0.88l-1.24,0.28l-1.04,-0.24l-0.14,-0.66l0.63,-0.58l0.92,0.26l0.7,-0.31Z",
            name: "Fiji"
        },
        FK: {
            path: "M274.36,425.85l1.44,1.08l-0.47,0.73l-3.0,0.89l-0.96,-1.0l-0.52,-0.05l-1.83,1.29l-0.73,-0.88l2.46,-1.64l1.93,0.76l1.67,-1.19Z",
            name: "Falkland Is."
        },
        NI: {
            path: "M202.33,252.67l0.81,-0.18l1.03,-1.02l-0.04,-0.88l0.68,-0.0l0.63,-0.54l0.97,0.22l1.53,-1.26l0.58,-0.99l1.17,0.34l2.41,-0.94l0.13,1.32l-0.81,1.94l0.1,2.74l-0.36,0.37l-0.11,1.75l-0.47,0.81l0.18,1.14l-1.73,-0.85l-0.71,0.27l-1.47,-0.6l-0.52,0.16l-4.01,-3.81Z",
            name: "Nicaragua"
        },
        NL: {
            path: "M430.31,143.39l0.6,-0.5l2.13,-4.8l3.2,-1.33l1.74,0.08l0.33,0.8l-0.59,2.92l-0.5,0.99l-1.26,0.0l-0.4,0.45l0.33,2.7l-2.2,-1.78l-2.62,0.58l-0.75,-0.11Z",
            name: "Netherlands"
        },
        NO: {
            path: "M491.44,67.41l6.8,2.89l-2.29,0.86l-0.15,0.65l2.33,2.38l-4.98,1.79l0.84,-2.45l-0.18,-0.48l-3.55,-1.8l-3.89,1.52l-1.42,3.38l-2.12,1.72l-2.64,-1.0l-3.11,0.21l-2.66,-2.22l-0.5,-0.01l-1.41,1.1l-1.44,0.17l-0.35,0.35l-0.32,2.47l-4.32,-0.64l-0.44,0.29l-0.58,2.11l-2.45,0.2l-4.15,7.68l-3.88,5.76l0.78,1.62l-0.64,1.16l-2.24,-0.06l-0.38,0.24l-1.66,3.89l0.15,5.17l1.57,2.04l-0.78,4.16l-2.02,2.48l-0.85,1.63l-1.3,-1.75l-0.58,-0.07l-4.87,4.19l-3.1,0.79l-3.16,-1.7l-0.85,-3.77l-0.77,-8.55l2.14,-2.31l6.55,-3.27l5.02,-4.17l10.63,-13.84l10.98,-8.7l5.35,-1.91l4.34,0.12l3.69,-3.64l4.49,0.19l4.37,-0.89ZM484.55,20.04l4.26,1.75l-3.1,2.55l-7.1,0.65l-7.08,-0.9l-0.37,-1.31l-0.37,-0.29l-3.44,-0.1l-2.08,-2.0l6.87,-1.44l3.9,1.31l2.39,-1.64l6.13,1.4ZM481.69,33.93l-4.45,1.74l-3.54,-0.99l1.12,-0.9l0.05,-0.58l-1.06,-1.22l4.22,-0.89l1.09,1.97l2.57,0.87ZM466.44,24.04l7.43,3.77l-5.41,1.86l-1.58,4.08l-2.26,1.2l-1.12,4.11l-2.61,0.18l-4.79,-2.86l1.84,-1.54l-0.1,-0.68l-3.69,-1.53l-4.77,-4.51l-1.73,-3.89l6.11,-1.82l1.54,1.92l3.57,-0.08l1.2,-1.96l3.32,-0.18l3.05,1.92Z",
            name: "Norway"
        },
        NA: {
            path: "M474.26,330.66l-0.97,0.04l-0.38,0.4l-0.07,8.9l-2.09,0.08l-0.39,0.4l-0.0,17.42l-1.98,1.23l-1.17,0.17l-2.44,-0.66l-0.48,-1.13l-0.99,-0.74l-0.54,0.05l-0.9,1.01l-1.53,-1.68l-0.93,-1.88l-1.99,-8.56l-0.06,-3.12l-0.33,-1.52l-2.3,-3.34l-1.91,-4.83l-1.96,-2.43l-0.12,-1.57l2.33,-0.79l1.43,0.07l1.81,1.13l10.23,-0.25l1.84,1.23l5.87,0.35ZM474.66,330.64l6.51,-1.6l1.9,0.39l-1.69,0.4l-1.31,0.83l-1.12,-0.94l-4.29,0.92Z",
            name: "Namibia"
        },
        NC: {
            path: "M838.78,341.24l-0.33,0.22l-2.9,-1.75l-3.26,-3.37l1.65,0.83l4.85,4.07Z",
            name: "New Caledonia"
        },
        NE: {
            path: "M454.75,226.53l1.33,1.37l0.48,0.07l1.27,-0.7l0.53,3.52l0.94,0.83l0.17,0.92l0.81,0.69l-0.44,0.95l-0.96,5.26l-0.13,3.22l-3.04,2.31l-1.22,3.57l1.02,1.24l-0.0,1.46l0.39,0.4l1.13,0.04l-0.9,1.25l-1.47,-2.42l-0.86,-0.29l-2.09,1.37l-1.74,-0.67l-1.45,-0.17l-0.85,0.35l-1.36,-0.07l-1.64,1.09l-1.06,0.05l-2.94,-1.28l-1.44,0.59l-1.01,-0.03l-0.97,-0.94l-2.7,-0.98l-2.69,0.3l-0.87,0.64l-0.47,1.6l-0.75,1.16l-0.12,1.53l-1.57,-1.1l-1.31,0.24l0.03,-0.81l-0.32,-0.41l-2.59,-0.52l-0.15,-1.16l-1.35,-1.6l-0.29,-1.0l0.13,-0.84l1.29,-0.08l1.08,-0.92l3.31,-0.22l2.22,-0.41l0.32,-0.34l0.2,-1.47l1.39,-1.88l-0.01,-5.66l3.36,-1.12l7.24,-5.12l8.42,-4.92l3.69,1.06Z",
            name: "Niger"
        },
        NG: {
            path: "M456.32,253.89l0.64,0.65l-0.28,1.04l-2.11,2.01l-2.03,5.18l-1.37,1.16l-1.15,3.18l-1.33,0.66l-1.46,-0.97l-1.21,0.16l-1.38,1.36l-0.91,0.24l-1.79,4.06l-2.33,0.81l-1.11,-0.07l-0.86,0.5l-1.71,-0.05l-1.19,-1.39l-0.89,-1.89l-1.77,-1.66l-3.95,-0.08l0.07,-5.21l0.42,-1.43l1.95,-2.3l-0.14,-0.91l0.43,-1.18l-0.53,-1.41l0.25,-2.92l0.72,-1.07l0.32,-1.34l0.46,-0.39l2.47,-0.28l2.34,0.89l1.15,1.02l1.28,0.04l1.22,-0.58l3.03,1.27l1.49,-0.14l1.36,-1.0l1.33,0.07l0.82,-0.35l3.45,0.8l1.82,-1.32l1.84,2.67l0.66,0.16Z",
            name: "Nigeria"
        },
        NZ: {
            path: "M857.8,379.65l1.86,3.12l0.44,0.18l0.3,-0.38l0.03,-1.23l0.38,0.27l0.57,2.31l2.02,0.94l1.81,0.27l1.57,-1.06l0.7,0.18l-1.15,3.59l-1.98,0.11l-0.74,1.2l0.2,1.11l-2.42,3.98l-1.49,0.92l-1.04,-0.85l1.21,-2.05l-0.81,-2.01l-2.63,-1.25l0.04,-0.57l1.82,-1.19l0.43,-2.34l-0.16,-2.03l-0.95,-1.82l-0.06,-0.72l-3.11,-3.64l-0.79,-1.52l1.56,1.45l1.76,0.66l0.65,2.34ZM853.83,393.59l0.57,1.24l0.59,0.16l1.42,-0.97l0.46,0.79l0.0,1.03l-2.47,3.48l-1.26,1.2l-0.06,0.5l0.55,0.87l-1.41,0.07l-2.33,1.38l-2.03,5.02l-3.02,2.16l-2.06,-0.06l-1.71,-1.04l-2.47,-0.2l-0.27,-0.73l1.22,-2.1l3.05,-2.94l1.62,-0.59l4.02,-2.82l1.57,-1.67l1.07,-2.16l0.88,-0.7l0.48,-1.75l1.24,-0.97l0.35,0.79Z",
            name: "New Zealand"
        },
        NP: {
            path: "M641.14,213.62l0.01,3.19l-1.74,0.04l-4.8,-0.86l-1.58,-1.39l-3.37,-0.34l-7.65,-3.7l0.8,-2.09l2.33,-1.7l1.77,0.75l2.49,1.76l1.38,0.41l0.99,1.35l1.9,0.52l1.99,1.17l5.49,0.9Z",
            name: "Nepal"
        },
        CI: {
            path: "M407.4,259.27l0.86,0.42l0.56,0.9l1.13,0.53l1.19,-0.61l0.97,-0.08l1.42,0.54l0.6,3.24l-1.03,2.08l-0.65,2.84l1.06,2.33l-0.06,0.53l-2.54,-0.47l-1.66,0.03l-3.06,0.46l-4.11,1.6l0.32,-3.06l-1.18,-1.31l-1.32,-0.66l0.42,-0.85l-0.2,-1.4l0.5,-0.67l0.01,-1.59l0.84,-0.32l0.26,-0.5l-1.15,-3.01l0.12,-0.5l0.51,-0.25l0.66,0.31l1.93,0.02l0.67,-0.71l0.71,-0.14l0.25,0.69l0.57,0.22l1.4,-0.61Z",
            name: "Côte d'Ivoire"
        },
        CH: {
            path: "M444.62,156.35l-0.29,0.87l0.18,0.53l1.13,0.58l1.0,0.1l-0.1,0.65l-0.79,0.38l-1.72,-0.37l-0.45,0.23l-0.45,1.04l-0.75,0.06l-0.84,-0.4l-1.32,1.0l-0.96,0.12l-0.88,-0.55l-0.81,-1.3l-0.49,-0.16l-0.63,0.26l0.02,-0.65l1.71,-1.66l0.1,-0.56l0.93,0.08l0.58,-0.46l1.99,0.02l0.66,-0.61l2.19,0.79Z",
            name: "Switzerland"
        },
        CO: {
            path: "M242.07,254.93l-1.7,0.59l-0.59,1.18l-1.7,1.69l-0.38,1.93l-0.67,1.43l0.31,0.57l1.03,0.13l0.25,0.9l0.57,0.64l-0.04,2.34l1.64,1.42l3.16,-0.24l1.26,0.28l1.67,2.06l0.41,0.13l4.09,-0.39l0.45,0.22l-0.92,1.95l-0.2,1.8l0.52,1.83l0.75,1.05l-1.12,1.1l0.07,0.63l0.84,0.51l0.74,1.29l-0.39,-0.45l-0.59,-0.01l-0.71,0.74l-4.71,-0.05l-0.4,0.41l0.03,1.57l0.33,0.39l1.11,0.2l-1.68,0.4l-0.29,0.38l-0.01,1.82l1.16,1.14l0.34,1.25l-1.05,7.05l-1.04,-0.87l1.26,-1.99l-0.13,-0.56l-2.18,-1.23l-1.38,0.2l-1.14,-0.38l-1.27,0.61l-1.55,-0.26l-1.38,-2.46l-1.23,-0.75l-0.85,-1.2l-1.67,-1.19l-0.86,0.13l-2.11,-1.32l-1.01,0.31l-1.8,-0.29l-0.52,-0.91l-3.09,-1.68l0.77,-0.52l-0.1,-1.12l0.41,-0.64l1.34,-0.32l2.0,-2.88l-0.11,-0.57l-0.66,-0.43l0.39,-1.38l-0.52,-2.1l0.49,-0.83l-0.4,-2.13l-0.97,-1.35l0.17,-0.66l0.86,-0.08l0.47,-0.75l-0.46,-1.63l1.41,-0.07l1.8,-1.69l0.93,-0.24l0.3,-0.38l0.45,-2.76l1.22,-1.0l1.44,-0.04l0.45,-0.5l1.91,0.12l2.93,-1.84l1.15,-1.14l0.91,0.46l-0.25,0.45Z",
            name: "Colombia"
        },
        CN: {
            path: "M740.23,148.97l4.57,1.3l2.8,2.17l0.98,2.9l0.38,0.27l3.8,0.0l2.32,-1.28l3.29,-0.75l-0.96,2.09l-1.02,1.28l-0.85,3.4l-1.52,2.73l-2.76,-0.5l-2.4,1.13l-0.21,0.45l0.64,2.57l-0.32,3.2l-0.94,0.06l-0.37,0.89l-0.91,-1.01l-0.64,0.07l-0.92,1.57l-3.73,1.25l-0.26,0.48l0.26,1.06l-1.5,-0.08l-1.09,-0.86l-0.56,0.06l-1.67,2.06l-2.7,1.56l-2.03,1.88l-3.4,0.83l-1.93,1.4l-1.15,0.34l0.33,-0.7l-0.41,-0.89l1.79,-1.79l0.02,-0.54l-1.32,-1.56l-0.48,-0.1l-2.24,1.09l-2.83,2.06l-1.51,1.83l-2.28,0.13l-1.55,1.49l-0.04,0.5l1.32,1.97l2.0,0.58l0.31,1.35l1.98,0.84l3.0,-1.96l2.0,1.02l1.49,0.11l0.22,0.83l-3.37,0.86l-1.12,1.48l-2.5,1.52l-1.29,1.99l0.14,0.56l2.57,1.48l0.97,2.7l3.17,4.63l-0.03,1.66l-1.35,0.65l-0.2,0.51l0.6,1.47l1.4,0.91l-0.89,3.82l-1.43,0.38l-3.85,6.44l-2.27,3.11l-6.78,4.57l-2.73,0.29l-1.45,1.04l-0.62,-0.61l-0.55,-0.01l-1.36,1.25l-3.39,1.27l-2.61,0.4l-1.1,2.79l-0.81,0.09l-0.49,-1.42l0.5,-0.85l-0.25,-0.59l-3.36,-0.84l-1.3,0.4l-2.31,-0.62l-0.94,-0.84l0.33,-1.28l-0.3,-0.49l-2.19,-0.46l-1.13,-0.93l-0.47,-0.02l-2.06,1.36l-4.29,0.28l-2.76,1.05l-0.28,0.43l0.32,2.53l-0.59,-0.03l-0.19,-1.34l-0.55,-0.34l-1.68,0.7l-2.46,-1.23l0.62,-1.87l-0.26,-0.51l-1.37,-0.44l-0.54,-2.22l-0.45,-0.3l-2.13,0.35l0.24,-2.48l2.39,-2.4l0.03,-4.31l-1.19,-0.92l-0.78,-1.49l-0.41,-0.21l-1.41,0.19l-1.98,-0.3l0.46,-1.07l-1.17,-1.7l-0.55,-0.11l-1.63,1.05l-2.25,-0.57l-2.89,1.73l-2.25,1.98l-1.75,0.29l-1.17,-0.71l-3.31,-0.65l-1.48,0.79l-1.04,1.27l-0.12,-1.17l-0.54,-0.34l-1.44,0.54l-5.55,-0.86l-1.98,-1.16l-1.89,-0.54l-0.99,-1.35l-1.34,-0.37l-2.55,-1.79l-2.01,-0.84l-1.21,0.56l-5.57,-3.45l-0.53,-2.31l1.19,0.25l0.48,-0.37l0.08,-1.42l-0.98,-1.56l0.15,-2.44l-2.69,-3.32l-4.12,-1.23l-0.67,-2.0l-1.92,-1.48l-0.38,-0.7l-0.51,-3.01l-1.52,-0.66l-0.7,0.13l-0.48,-2.05l0.55,-0.51l-0.09,-0.82l2.03,-1.19l1.6,-0.54l2.56,0.38l0.42,-0.22l0.85,-1.7l3.0,-0.33l1.1,-1.26l4.05,-1.77l0.39,-0.91l-0.17,-1.44l1.45,-0.67l0.2,-0.52l-2.07,-4.9l4.51,-1.12l1.37,-0.73l1.89,-5.51l4.98,0.86l1.51,-1.7l0.11,-2.87l1.99,-0.38l1.83,-2.06l0.49,-0.13l0.68,2.08l2.23,1.77l3.44,1.16l1.55,2.29l-0.92,3.49l0.96,1.67l6.54,1.13l2.95,1.87l1.47,0.35l1.06,2.62l1.53,1.91l3.05,0.08l5.14,0.67l3.37,-0.41l2.36,0.43l3.65,1.8l3.06,0.04l1.45,0.88l2.87,-1.59l3.95,-1.02l3.83,-0.14l3.06,-1.14l1.77,-1.6l1.72,-1.01l0.17,-0.49l-1.1,-2.05l1.02,-1.54l4.02,0.8l2.45,-1.61l3.76,-1.19l1.96,-2.13l1.63,-0.83l3.51,-0.4l1.92,0.34l0.46,-0.3l0.17,-1.5l-2.27,-2.22l-2.11,-1.09l-2.18,1.11l-2.32,-0.47l-1.29,0.32l-0.4,-0.82l2.73,-5.16l3.02,1.06l3.53,-2.06l0.18,-1.68l2.16,-3.35l1.49,-1.35l-0.03,-1.85l-1.07,-0.85l1.54,-1.26l2.98,-0.59l3.23,-0.09l3.64,0.99l2.04,1.16l3.29,6.71l0.92,3.19ZM696.92,237.31l-1.87,1.08l-1.63,-0.64l-0.06,-1.79l1.03,-0.98l2.58,-0.69l1.16,0.05l0.3,0.54l-0.98,1.06l-0.53,1.37Z",
            name: "China"
        },
        CM: {
            path: "M457.92,257.49l1.05,1.91l-1.4,0.16l-1.05,-0.23l-0.45,0.22l-0.54,1.19l0.08,0.45l1.48,1.47l1.05,0.45l1.01,2.46l-1.52,2.99l-0.68,0.68l-0.13,3.69l2.38,3.84l1.09,0.8l0.24,2.48l-3.67,-1.14l-11.27,-0.13l0.23,-1.79l-0.98,-1.66l-1.19,-0.54l-0.44,-0.97l-0.6,-0.42l1.71,-4.27l0.75,-0.13l1.38,-1.36l0.65,-0.03l1.71,0.99l1.93,-1.12l1.14,-3.18l1.38,-1.17l2.0,-5.14l2.17,-2.13l0.3,-1.64l-0.86,-0.88l0.03,-0.33l0.94,1.28l0.07,3.22Z",
            name: "Cameroon"
        },
        CL: {
            path: "M246.5,429.18l-3.14,1.83l-0.57,3.16l-0.64,0.05l-2.68,-1.06l-2.82,-2.33l-3.04,-1.89l-0.69,-1.85l0.63,-2.14l-1.21,-2.11l-0.31,-5.37l1.01,-2.91l2.57,-2.38l-0.18,-0.68l-3.16,-0.77l2.05,-2.47l0.77,-4.65l2.32,0.9l0.54,-0.29l1.31,-6.31l-0.22,-0.44l-1.68,-0.8l-0.56,0.28l-0.7,3.36l-0.81,-0.22l1.56,-9.41l1.15,-2.24l-0.71,-2.82l-0.18,-2.84l1.01,-0.33l3.26,-9.14l1.07,-4.22l-0.56,-4.21l0.74,-2.34l-0.29,-3.27l1.46,-3.34l2.04,-16.59l-0.66,-7.76l1.03,-0.53l0.54,-0.9l0.79,1.14l0.32,1.78l1.25,1.16l-0.69,2.55l1.33,2.9l0.97,3.59l0.46,0.29l1.5,-0.3l0.11,0.23l-0.76,2.44l-2.57,1.23l-0.23,0.37l0.08,4.33l-0.46,0.77l0.56,1.21l-1.58,1.51l-1.68,2.62l-0.89,2.47l0.2,2.7l-1.48,2.73l1.12,5.09l0.64,0.61l-0.01,2.29l-1.38,2.68l0.01,2.4l-1.89,2.04l0.02,2.75l0.69,2.57l-1.43,1.13l-1.26,5.68l0.39,3.51l-0.97,0.89l0.58,3.5l1.02,1.14l-0.65,1.02l0.15,0.57l1.0,0.53l0.16,0.69l-1.03,0.85l0.26,1.75l-0.89,4.03l-1.31,2.66l0.24,1.75l-0.71,1.83l-1.99,1.7l0.3,3.67l0.88,1.19l1.58,0.01l0.01,2.21l1.04,1.95l5.98,0.63ZM248.69,430.79l0.0,7.33l0.4,0.4l3.52,0.05l-0.44,0.75l-1.94,0.98l-2.49,-0.37l-1.88,-1.06l-2.55,-0.49l-5.59,-3.71l-2.38,-2.63l4.1,2.48l3.32,1.23l0.45,-0.12l1.29,-1.57l0.83,-2.32l2.05,-1.24l1.31,0.29Z",
            name: "Chile"
        },
        CA: {
            path: "M280.06,145.6l-1.67,2.88l0.07,0.49l0.5,0.04l1.46,-0.98l1.0,0.42l-0.56,0.72l0.17,0.62l2.22,0.89l1.35,-0.71l1.95,0.78l-0.66,2.01l0.5,0.51l1.32,-0.42l0.98,3.17l-0.91,2.41l-0.8,0.08l-1.23,-0.45l0.47,-2.25l-0.89,-0.83l-0.48,0.06l-2.78,2.63l-0.34,-0.02l1.02,-0.85l-0.14,-0.69l-2.4,-0.77l-7.4,0.08l-0.17,-0.41l1.3,-0.94l0.02,-0.64l-0.73,-0.58l1.85,-1.74l2.57,-5.16l1.47,-1.79l1.99,-1.05l0.46,0.06l-1.53,2.45ZM68.32,74.16l4.13,0.95l4.02,2.14l2.61,0.4l2.47,-1.89l2.88,-1.31l3.85,0.48l3.71,-1.94l3.82,-1.04l1.56,1.68l0.49,0.08l1.87,-1.04l0.65,-1.98l1.24,0.35l4.16,3.94l0.54,0.01l2.75,-2.49l0.26,2.59l0.49,0.35l3.08,-0.73l1.04,-1.27l2.73,0.23l3.83,1.86l5.86,1.61l3.47,0.75l2.44,-0.26l2.73,1.78l-2.98,1.81l-0.19,0.41l0.31,0.32l4.53,0.92l6.87,-0.5l2.0,-0.69l2.49,2.39l0.53,0.02l2.72,-2.16l-0.02,-0.64l-2.16,-1.54l1.15,-1.06l4.83,-0.61l1.84,0.95l2.48,2.31l3.01,-0.23l4.55,1.92l3.85,-0.67l3.61,0.1l0.41,-0.44l-0.25,-2.36l1.79,-0.61l3.49,1.32l-0.01,3.77l0.31,0.39l0.45,-0.22l1.48,-3.16l1.74,0.1l0.41,-0.3l1.13,-4.37l-2.78,-3.11l-2.8,-1.74l0.19,-4.64l2.71,-3.07l2.98,0.67l2.41,1.95l3.19,4.8l-1.99,1.97l0.21,0.68l4.33,0.84l-0.01,4.15l0.25,0.37l0.44,-0.09l3.07,-3.15l2.54,2.39l-0.61,3.33l2.42,2.88l0.61,0.0l2.61,-3.08l1.88,-3.82l0.17,-4.58l6.72,0.94l3.13,2.04l0.13,1.82l-1.76,2.19l-0.01,0.49l1.66,2.16l-0.26,1.71l-4.68,2.8l-3.28,0.61l-2.47,-1.2l-0.55,0.23l-0.73,2.04l-2.38,3.43l-0.74,1.77l-2.74,2.57l-3.44,0.25l-2.21,1.78l-0.28,2.53l-2.82,0.55l-3.12,3.22l-2.72,4.31l-1.03,3.17l-0.14,4.31l0.33,0.41l3.44,0.57l2.24,5.95l0.45,0.23l3.4,-0.69l4.52,1.51l2.43,1.31l1.91,1.73l3.1,0.96l2.62,1.46l6.6,0.54l-0.35,2.74l0.81,3.53l1.81,3.78l3.83,3.3l0.45,0.04l2.1,-1.28l1.37,-3.69l-1.31,-5.38l-1.45,-1.58l3.57,-1.47l2.84,-2.46l1.52,-2.8l-0.25,-2.55l-1.7,-3.07l-2.85,-2.61l2.8,-3.95l-1.08,-3.37l-0.79,-5.67l1.36,-0.7l6.76,1.41l2.12,-0.96l5.12,3.36l1.05,1.61l4.08,0.26l-0.06,2.87l0.83,4.7l0.3,0.32l2.16,0.54l1.73,2.06l0.5,0.09l3.63,-2.03l2.52,-4.19l1.26,-1.32l7.6,11.72l-0.92,2.04l0.16,0.51l3.3,1.97l2.22,1.98l4.1,0.98l1.43,0.99l0.95,2.79l2.1,0.68l0.84,1.08l0.17,3.45l-3.37,2.26l-4.22,1.24l-3.06,2.63l-4.06,0.51l-5.35,-0.69l-6.39,0.2l-2.3,2.41l-3.26,1.51l-6.47,7.15l-0.06,0.48l0.44,0.19l2.13,-0.52l4.17,-4.24l5.12,-2.62l3.52,-0.3l1.69,1.21l-2.12,2.21l0.81,3.47l1.02,2.61l3.47,1.6l4.14,-0.45l2.15,-2.8l0.26,1.48l1.14,0.8l-2.56,1.69l-5.5,1.82l-2.54,1.27l-2.74,2.15l-1.4,-0.16l-0.07,-2.01l4.14,-2.44l0.18,-0.45l-0.39,-0.29l-6.63,0.45l-1.39,-1.49l-0.14,-4.43l-1.11,-0.91l-1.82,0.39l-0.66,-0.66l-0.6,0.03l-1.91,2.39l-0.82,2.52l-0.8,1.27l-1.67,0.56l-0.46,0.76l-8.31,0.07l-1.21,0.62l-2.35,1.97l-0.71,-0.14l-1.37,0.96l-1.12,-0.48l-4.74,1.26l-0.9,1.17l0.21,0.62l1.73,0.3l-1.81,0.31l-1.85,0.81l-2.11,-0.13l-2.95,1.78l-0.69,-0.09l1.39,-2.1l1.73,-1.21l0.1,-2.29l1.16,-1.99l0.49,0.53l2.03,0.42l1.2,-1.16l0.02,-0.47l-2.66,-3.51l-2.28,-0.61l-5.64,-0.71l-0.4,-0.57l-0.79,0.13l0.2,-0.41l-0.22,-0.55l-0.68,-0.26l0.19,-1.26l-0.78,-0.73l0.31,-0.64l-0.29,-0.57l-2.6,-0.44l-0.75,-1.63l-0.94,-0.66l-4.31,-0.65l-1.13,1.19l-1.48,0.59l-0.85,1.06l-2.83,-0.76l-2.09,0.39l-2.39,-0.97l-4.24,-0.7l-0.57,-0.4l-0.41,-1.63l-0.4,-0.3l-0.85,0.02l-0.39,0.4l-0.01,0.85l-69.13,-0.01l-6.51,-4.52l-4.5,-1.38l-1.26,-2.66l0.33,-1.93l-0.23,-0.43l-3.01,-1.35l-0.55,-2.77l-2.89,-2.38l-0.04,-1.45l1.39,-1.83l-0.28,-2.55l-4.16,-2.2l-4.07,-6.6l-4.02,-3.22l-1.3,-1.88l-0.5,-0.13l-2.51,1.21l-2.23,1.87l-3.85,-3.88l-2.44,-1.04l-2.22,-0.13l0.03,-37.49ZM260.37,148.65l3.04,0.76l2.26,1.2l-3.78,-0.95l-1.53,-1.01ZM249.4,3.81l6.68,0.49l5.32,0.79l4.26,1.57l-0.07,1.1l-5.85,2.53l-6.02,1.21l-2.39,1.39l-0.18,0.45l0.39,0.29l4.01,-0.02l-4.65,2.82l-4.2,1.74l-4.19,4.59l-5.03,0.92l-1.67,1.15l-7.47,0.59l-0.37,0.37l0.32,0.42l2.41,0.49l-0.81,0.47l-0.12,0.59l1.83,2.41l-2.02,1.59l-3.81,1.51l-1.32,2.16l-3.38,1.53l-0.22,0.48l0.35,1.19l0.4,0.29l3.88,-0.18l0.03,0.61l-6.33,2.95l-6.41,-1.4l-7.43,0.79l-3.72,-0.62l-4.4,-0.25l-0.23,-1.83l4.29,-1.11l0.28,-0.51l-1.1,-3.45l1.0,-0.25l6.58,2.28l0.47,-0.16l-0.05,-0.49l-3.41,-3.45l-3.58,-0.98l1.48,-1.55l4.34,-1.29l0.97,-2.19l-0.16,-0.48l-3.42,-2.13l-0.81,-2.26l6.2,0.22l2.24,0.58l3.91,-2.1l0.2,-0.43l-0.35,-0.32l-5.64,-0.67l-8.73,0.36l-4.26,-1.9l-2.12,-2.4l-2.78,-1.66l-0.41,-1.52l3.31,-1.03l2.93,-0.2l4.91,-0.99l3.7,-2.27l2.87,0.3l2.62,1.67l0.56,-0.14l1.82,-3.2l3.13,-0.94l4.44,-0.69l7.53,-0.26l1.48,0.67l7.19,-1.06l10.8,0.79ZM203.85,57.54l0.01,0.42l1.97,2.97l0.68,-0.02l2.24,-3.72l5.95,-1.86l4.01,4.64l-0.35,2.91l0.5,0.43l4.95,-1.36l2.32,-1.8l5.31,2.28l3.27,2.11l0.3,1.84l0.48,0.33l4.42,-0.99l2.64,2.87l5.97,1.77l2.06,1.72l2.11,3.71l-4.19,1.86l-0.01,0.73l5.9,2.83l3.94,0.94l3.78,3.95l3.46,0.25l-0.63,2.37l-4.11,4.47l-2.76,-1.56l-3.9,-3.94l-3.59,0.41l-0.33,0.34l-0.19,2.72l2.63,2.38l3.42,1.89l0.94,0.97l1.55,3.75l-0.7,2.29l-2.74,-0.92l-6.25,-3.15l-0.51,0.13l0.05,0.52l6.07,5.69l0.18,0.59l-6.09,-1.39l-5.31,-2.24l-2.63,-1.66l0.6,-0.77l-0.12,-0.6l-7.39,-4.01l-0.59,0.37l0.03,0.79l-6.73,0.6l-1.69,-1.1l1.36,-2.46l4.51,-0.07l5.15,-0.52l0.31,-0.6l-0.74,-1.3l0.78,-1.84l3.21,-4.05l-0.67,-2.35l-1.11,-1.6l-3.84,-2.1l-4.35,-1.28l0.91,-0.63l0.06,-0.61l-2.65,-2.75l-2.34,-0.36l-1.89,-1.46l-0.53,0.03l-1.24,1.23l-4.36,0.55l-9.04,-0.99l-9.26,-1.98l-1.6,-1.22l2.22,-1.77l0.13,-0.44l-0.38,-0.27l-3.22,-0.02l-0.72,-4.25l1.83,-4.04l2.42,-1.85l5.5,-1.1l-1.39,2.35ZM261.19,159.33l2.07,0.61l1.44,-0.04l-1.15,0.63l-2.94,-1.23l-0.4,-0.68l0.36,-0.37l0.61,1.07ZM230.83,84.39l-2.37,0.18l-0.49,-1.63l0.93,-2.09l1.94,-0.51l1.62,0.99l0.02,1.52l-1.66,1.54ZM229.43,58.25l0.11,0.65l-4.87,-0.21l-2.72,0.62l-3.1,-2.57l0.08,-1.26l0.86,-0.23l5.57,0.51l4.08,2.5ZM222.0,105.02l-0.72,1.49l-0.63,-0.19l-0.48,-0.84l0.81,-0.99l0.65,0.05l0.37,0.46ZM183.74,38.32l2.9,1.7l4.79,-0.01l1.84,1.46l-0.49,1.68l0.23,0.48l2.82,1.14l1.76,1.26l7.01,0.65l4.1,-1.1l5.03,-0.43l3.93,0.35l2.48,1.77l0.46,1.7l-1.3,1.1l-3.56,1.01l-3.23,-0.59l-7.17,0.76l-5.09,0.09l-3.99,-0.6l-6.42,-1.54l-0.79,-2.51l-0.3,-2.49l-2.64,-2.5l-5.32,-0.72l-2.52,-1.4l0.68,-1.57l4.78,0.31ZM207.38,91.35l0.4,1.56l0.56,0.26l1.06,-0.52l1.32,0.96l5.42,2.57l0.2,1.68l0.46,0.35l1.68,-0.28l1.15,0.85l-1.55,0.87l-3.61,-0.88l-1.32,-1.69l-0.57,-0.06l-2.45,2.1l-3.12,1.79l-0.7,-1.87l-0.42,-0.26l-2.16,0.24l1.39,-1.39l0.32,-3.14l0.76,-3.35l1.18,0.22ZM215.49,102.6l-2.67,1.95l-1.4,-0.07l-0.3,-0.58l1.53,-1.48l2.84,0.18ZM202.7,24.12l2.53,1.59l-2.87,1.4l-4.53,4.05l-4.25,0.38l-5.03,-0.68l-2.45,-2.04l0.03,-1.62l1.82,-1.37l0.14,-0.45l-0.38,-0.27l-4.45,0.04l-2.59,-1.76l-1.41,-2.29l1.57,-2.32l1.62,-1.66l2.44,-0.39l0.25,-0.65l-0.6,-0.74l4.86,-0.25l3.24,3.11l8.16,2.3l1.9,3.61ZM187.47,59.2l-2.76,3.49l-2.38,-0.15l-1.44,-3.84l0.04,-2.2l1.19,-1.88l2.3,-1.23l5.07,0.17l4.11,1.02l-3.24,3.72l-2.88,0.89ZM186.07,48.79l-1.08,1.53l-3.34,-0.34l-2.56,-1.1l1.03,-1.75l3.25,-1.23l1.95,1.58l0.75,1.3ZM185.71,35.32l-5.3,-0.2l-0.32,-0.71l4.31,0.07l1.3,0.84ZM180.68,32.48l-3.34,1.0l-1.79,-1.1l-0.98,-1.87l-0.15,-1.73l4.1,0.53l2.67,1.7l-0.51,1.47ZM180.9,76.31l-1.1,1.08l-3.13,-1.23l-2.12,0.43l-2.71,-1.57l1.72,-1.09l1.55,-1.72l3.81,1.9l1.98,2.2ZM169.74,54.87l2.96,0.97l4.17,-0.57l0.41,0.88l-2.14,2.11l0.09,0.64l3.55,1.92l-0.4,3.72l-3.79,1.65l-2.17,-0.35l-1.72,-1.74l-6.02,-3.5l0.03,-0.85l4.68,0.54l0.4,-0.21l-0.05,-0.45l-2.48,-2.81l2.46,-1.95ZM174.45,40.74l1.37,1.73l0.07,2.44l-1.05,3.45l-3.79,0.47l-2.32,-0.69l0.05,-2.64l-0.44,-0.41l-3.68,0.35l-0.12,-3.1l2.45,0.1l3.67,-1.73l3.41,0.29l0.37,-0.26ZM170.05,31.55l0.67,1.56l-3.33,-0.49l-4.22,-1.77l-4.35,-0.16l1.4,-0.94l-0.06,-0.7l-2.81,-1.23l-0.12,-1.39l4.39,0.68l6.62,1.98l1.81,2.47ZM134.5,58.13l-1.02,1.82l0.45,0.58l5.4,-1.39l3.33,2.29l0.49,-0.03l2.6,-2.23l1.94,1.32l2.0,4.5l0.7,0.06l1.3,-2.29l-1.63,-4.46l1.69,-0.54l2.31,0.71l2.65,1.81l2.49,7.92l8.48,4.27l-0.19,1.35l-3.79,0.33l-0.26,0.67l1.4,1.49l-0.58,1.1l-4.23,-0.64l-4.43,-1.19l-3.0,0.28l-4.66,1.47l-10.52,1.04l-1.43,-2.02l-3.42,-1.2l-2.21,0.43l-2.51,-2.86l4.84,-1.05l3.6,0.19l3.27,-0.78l0.31,-0.39l-0.31,-0.39l-4.84,-1.06l-8.79,0.27l-0.85,-1.07l5.26,-1.66l0.27,-0.45l-0.4,-0.34l-3.8,0.06l-3.81,-1.06l1.81,-3.01l1.66,-1.79l6.48,-2.81l1.97,0.71ZM158.7,56.61l-1.7,2.44l-3.2,-2.75l0.37,-0.3l3.11,-0.18l1.42,0.79ZM149.61,42.73l1.01,1.89l0.5,0.18l2.14,-0.82l2.23,0.19l0.36,2.04l-1.33,2.09l-8.28,0.76l-6.35,2.15l-3.41,0.1l-0.19,-0.96l4.9,-2.08l0.23,-0.46l-0.41,-0.31l-11.25,0.59l-2.89,-0.74l3.04,-4.44l2.14,-1.32l6.81,1.69l4.58,3.06l4.37,0.39l0.36,-0.63l-3.36,-4.6l1.85,-1.53l2.18,0.51l0.77,2.26ZM144.76,34.41l-4.36,1.44l-3.0,-1.4l1.46,-1.24l3.47,-0.52l2.96,0.71l-0.52,1.01ZM145.13,29.83l-1.9,0.66l-3.67,-0.0l2.27,-1.61l3.3,0.95ZM118.92,65.79l-6.03,2.02l-1.33,-1.9l-5.38,-2.28l2.59,-5.05l2.16,-3.14l-0.02,-0.48l-1.97,-2.41l7.64,-0.7l3.6,1.02l6.3,0.27l4.42,2.95l-2.53,0.98l-6.24,3.43l-3.1,3.28l-0.11,2.01ZM129.54,35.53l-0.28,3.37l-1.72,1.62l-2.33,0.28l-4.61,2.19l-3.86,0.76l-2.64,-0.87l3.72,-3.4l5.01,-3.34l3.72,0.07l3.0,-0.67ZM111.09,152.69l-0.67,0.24l-3.85,-1.37l-0.83,-1.17l-2.12,-1.07l-0.66,-1.02l-2.4,-0.55l-0.74,-1.71l6.02,1.45l2.0,2.55l2.52,1.39l0.73,1.27ZM87.8,134.64l0.89,0.29l1.86,-0.21l-0.65,3.34l1.69,2.33l-1.31,-1.33l-0.99,-1.62l-1.17,-0.98l-0.33,-1.82Z",
            name: "Canada"
        },
        CG: {
            path: "M466.72,276.48l-0.1,1.03l-1.25,2.97l-0.19,3.62l-0.46,1.78l-0.23,0.63l-1.61,1.19l-1.21,1.39l-1.09,2.43l0.04,2.09l-3.25,3.24l-0.5,-0.24l-0.5,-0.83l-1.36,-0.02l-0.98,0.89l-1.68,-0.99l-1.54,1.24l-1.52,-1.96l1.57,-1.14l0.11,-0.52l-0.77,-1.35l2.1,-0.66l0.39,-0.73l1.05,0.82l2.21,0.11l1.12,-1.37l0.37,-1.81l-0.27,-2.09l-1.13,-1.5l1.0,-2.69l-0.13,-0.45l-0.92,-0.58l-1.6,0.17l-0.51,-0.94l0.1,-0.61l2.75,0.09l3.97,1.24l0.51,-0.33l0.17,-1.28l1.24,-2.21l1.28,-1.14l2.76,0.49Z",
            name: "Congo"
        },
        CF: {
            path: "M461.16,278.2l-0.26,-1.19l-1.09,-0.77l-0.84,-1.17l-0.29,-1.0l-1.04,-1.15l0.08,-3.43l0.58,-0.49l1.16,-2.35l1.85,-0.17l0.61,-0.62l0.97,0.58l3.15,-0.96l2.48,-1.92l0.02,-0.96l2.81,0.02l2.36,-1.17l1.93,-2.85l1.16,-0.93l1.11,-0.3l0.27,0.86l1.34,1.47l-0.39,2.01l0.3,1.01l4.01,2.75l0.17,0.93l2.63,2.31l0.6,1.44l2.08,1.4l-3.84,-0.21l-1.94,0.88l-1.23,-0.49l-2.67,1.2l-1.29,-0.18l-0.51,0.36l-0.6,1.22l-3.35,-0.65l-1.57,-0.91l-2.42,-0.83l-1.45,0.91l-0.97,1.27l-0.26,1.56l-3.22,-0.43l-1.49,1.33l-0.94,1.62Z",
            name: "Central African Rep."
        },
        CD: {
            path: "M487.01,272.38l2.34,-0.14l1.35,1.84l1.34,0.45l0.86,-0.39l1.21,0.12l1.07,-0.41l0.54,0.89l2.04,1.54l-0.14,2.72l0.7,0.54l-1.38,1.13l-1.53,2.54l-0.17,2.05l-0.59,1.08l-0.02,1.72l-0.72,0.84l-0.66,3.01l0.63,1.32l-0.44,4.26l0.64,1.47l-0.37,1.22l0.86,1.8l1.53,1.41l0.3,1.26l0.44,0.5l-4.08,0.75l-0.92,1.81l0.51,1.34l-0.74,5.43l0.17,0.38l2.45,1.46l0.54,-0.1l0.12,1.62l-1.28,-0.01l-1.85,-2.35l-1.94,-0.45l-0.48,-1.13l-0.55,-0.2l-1.41,0.74l-1.71,-0.3l-1.01,-1.18l-2.49,-0.19l-0.44,-0.77l-1.98,-0.21l-2.88,0.36l0.11,-2.41l-0.85,-1.13l-0.16,-1.36l0.32,-1.73l-0.46,-0.89l-0.04,-1.49l-0.4,-0.39l-2.53,0.02l0.1,-0.41l-0.39,-0.49l-1.28,0.01l-0.43,0.45l-1.62,0.32l-0.83,1.79l-1.09,-0.28l-2.4,0.52l-1.37,-1.91l-1.3,-3.3l-0.38,-0.27l-7.39,-0.03l-2.46,0.42l0.5,-0.45l0.37,-1.47l0.66,-0.38l0.92,0.08l0.73,-0.82l0.87,0.02l0.31,0.68l1.4,0.36l3.59,-3.63l0.01,-2.23l1.02,-2.29l2.69,-2.39l0.43,-0.99l0.49,-1.96l0.17,-3.51l1.25,-2.95l0.36,-3.14l0.86,-1.13l1.1,-0.66l3.57,1.73l3.65,0.73l0.46,-0.21l0.8,-1.46l1.24,0.19l2.61,-1.17l0.81,0.44l1.04,-0.03l0.59,-0.66l0.7,-0.16l1.81,0.25Z",
            name: "Dem. Rep. Congo"
        },
        CZ: {
            path: "M458.46,144.88l1.22,1.01l1.47,0.23l0.13,0.93l1.36,0.68l0.54,-0.2l0.24,-0.55l1.15,0.25l0.53,1.09l1.68,0.18l0.6,0.84l-1.04,0.73l-0.96,1.28l-1.6,0.17l-0.55,0.56l-1.04,-0.46l-1.05,0.15l-2.12,-0.96l-1.05,0.34l-1.2,1.12l-1.56,-0.87l-2.57,-2.1l-0.53,-1.88l4.7,-2.52l0.71,0.26l0.9,-0.28Z",
            name: "Czech Rep."
        },
        CY: {
            path: "M504.36,193.47l0.43,0.28l-1.28,0.57l-0.92,-0.28l-0.24,-0.46l2.01,-0.13Z",
            name: "Cyprus"
        },
        CR: {
            path: "M211.34,258.05l0.48,0.99l1.6,1.6l-0.54,0.45l0.29,1.42l-0.25,1.19l-1.09,-0.59l-0.05,-1.25l-2.46,-1.42l-0.28,-0.77l-0.66,-0.45l-0.45,-0.0l-0.11,1.04l-1.32,-0.95l0.31,-1.3l-0.36,-0.6l0.31,-0.27l1.42,0.58l1.29,-0.14l0.56,0.56l0.74,0.17l0.55,-0.27Z",
            name: "Costa Rica"
        },
        CU: {
            path: "M221.21,227.25l1.27,1.02l2.19,-0.28l4.43,3.33l2.08,0.43l-0.1,0.38l0.36,0.5l1.75,0.1l1.48,0.84l-3.11,0.51l-4.15,-0.03l0.77,-0.67l-0.04,-0.64l-1.2,-0.74l-1.49,-0.16l-0.7,-0.61l-0.56,-1.4l-0.4,-0.25l-1.34,0.1l-2.2,-0.66l-0.88,-0.58l-3.18,-0.4l-0.27,-0.16l0.58,-0.74l-0.36,-0.29l-2.72,-0.05l-1.7,1.29l-0.91,0.03l-0.61,0.69l-1.01,0.22l1.11,-1.29l1.01,-0.52l3.69,-1.01l3.98,0.21l2.21,0.84Z",
            name: "Cuba"
        },
        SZ: {
            path: "M500.35,351.36l0.5,2.04l-0.38,0.89l-1.05,0.21l-1.23,-1.2l-0.02,-0.64l0.83,-1.57l1.34,0.27Z",
            name: "Swaziland"
        },
        SY: {
            path: "M511.0,199.79l0.05,-1.33l0.54,-1.36l1.28,-0.99l0.13,-0.45l-0.41,-1.11l-1.14,-0.36l-0.19,-1.74l0.52,-1.0l1.29,-1.21l0.2,-1.18l0.59,0.23l2.62,-0.76l1.36,0.52l2.06,-0.01l2.95,-1.08l3.25,-0.26l-0.67,0.94l-1.28,0.66l-0.21,0.4l0.23,2.01l-0.88,3.19l-10.15,5.73l-2.15,-0.85Z",
            name: "Syria"
        },
        KG: {
            path: "M621.35,172.32l-3.87,1.69l-0.96,1.18l-3.04,0.34l-1.13,1.86l-2.36,-0.35l-1.99,0.63l-2.39,1.4l0.06,0.95l-0.4,0.37l-4.52,0.43l-3.02,-0.93l-2.37,0.17l0.11,-0.79l2.32,0.42l1.13,-0.88l1.99,0.2l3.21,-2.14l-0.03,-0.69l-2.97,-1.57l-1.94,0.65l-1.22,-0.74l1.71,-1.58l-0.12,-0.67l-0.36,-0.15l0.32,-0.77l1.36,-0.35l4.02,1.02l0.49,-0.3l0.35,-1.59l1.09,-0.48l3.42,1.22l1.11,-0.31l7.64,0.39l1.16,1.0l1.23,0.39Z",
            name: "Kyrgyzstan"
        },
        KE: {
            path: "M506.26,284.69l1.87,-2.56l0.93,-2.15l-1.38,-4.08l-1.06,-1.6l2.82,-2.75l0.79,0.26l0.12,1.41l0.86,0.83l1.9,0.11l3.28,2.13l3.57,0.44l1.05,-1.12l1.96,-0.9l0.82,0.68l1.16,0.09l-1.78,2.45l0.03,9.12l1.3,1.94l-1.37,0.78l-0.67,1.03l-1.08,0.46l-0.34,1.67l-0.81,1.07l-0.45,1.55l-0.68,0.56l-3.2,-2.23l-0.35,-1.58l-8.86,-4.98l0.14,-1.6l-0.57,-1.04Z",
            name: "Kenya"
        },
        SS: {
            path: "M481.71,263.34l1.07,-0.72l1.2,-3.18l1.36,-0.26l1.61,1.99l0.87,0.34l1.1,-0.41l1.5,0.07l0.57,0.53l2.49,0.0l0.44,-0.63l1.07,-0.4l0.45,-0.84l0.59,-0.33l1.9,1.33l1.6,-0.2l2.83,-3.33l-0.32,-2.21l1.59,-0.52l-0.24,1.6l0.3,1.83l1.35,1.18l0.2,1.87l0.35,0.41l0.02,1.53l-0.23,0.47l-1.42,0.25l-0.85,1.44l0.3,0.6l1.4,0.16l1.11,1.08l0.59,1.13l1.03,0.53l1.28,2.36l-4.41,3.98l-1.74,0.01l-1.89,0.55l-1.47,-0.52l-1.15,0.57l-2.96,-2.62l-1.3,0.49l-1.06,-0.15l-0.79,0.39l-0.82,-0.22l-1.8,-2.7l-1.91,-1.1l-0.66,-1.5l-2.62,-2.32l-0.18,-0.94l-2.37,-1.6Z",
            name: "S. Sudan"
        },
        SR: {
            path: "M283.12,270.19l2.1,0.53l-1.08,1.95l0.2,1.72l0.93,1.49l-0.59,2.03l-0.43,0.71l-1.12,-0.42l-1.32,0.22l-0.93,-0.2l-0.46,0.26l-0.25,0.73l0.33,0.7l-0.89,-0.13l-1.39,-1.97l-0.31,-1.34l-0.97,-0.31l-0.89,-1.47l0.35,-1.61l1.45,-0.82l0.33,-1.87l2.61,0.44l0.57,-0.47l1.75,-0.16Z",
            name: "Suriname"
        },
        KH: {
            path: "M689.52,249.39l0.49,1.45l-0.28,2.74l-4.0,1.86l-0.16,0.6l0.68,0.95l-2.06,0.17l-2.05,0.97l-1.82,-0.32l-2.12,-3.7l-0.55,-2.85l1.4,-1.85l3.02,-0.45l2.23,0.35l2.01,0.98l0.51,-0.14l0.95,-1.48l1.74,0.74Z",
            name: "Cambodia"
        },
        SV: {
            path: "M195.8,250.13l1.4,-1.19l2.24,1.45l0.98,-0.27l0.44,0.2l-0.27,1.05l-1.14,-0.03l-3.64,-1.21Z",
            name: "El Salvador"
        },
        SK: {
            path: "M476.82,151.17l-1.14,1.9l-2.73,-0.92l-0.82,0.2l-0.74,0.8l-3.46,0.73l-0.47,0.69l-1.76,0.33l-1.88,-1.0l-0.18,-0.81l0.38,-0.75l1.87,-0.32l1.74,-1.89l0.83,0.16l0.79,-0.34l1.51,1.04l1.34,-0.63l1.25,0.3l1.65,-0.42l1.81,0.95Z",
            name: "Slovakia"
        },
        KR: {
            path: "M737.51,185.84l0.98,-0.1l0.87,-1.17l2.69,-0.32l0.33,-0.29l1.76,2.79l0.58,1.76l0.02,3.12l-0.8,1.32l-2.21,0.55l-1.93,1.13l-1.8,0.19l-0.2,-1.1l0.43,-2.28l-0.95,-2.56l1.43,-0.37l0.23,-0.62l-1.43,-2.06Z",
            name: "Korea"
        },
        SI: {
            path: "M456.18,162.07l-0.51,-1.32l0.18,-1.05l1.69,0.2l1.42,-0.71l2.09,-0.07l0.62,-0.51l0.21,0.47l-1.61,0.67l-0.44,1.34l-0.66,0.24l-0.26,0.82l-1.22,-0.49l-0.84,0.46l-0.69,-0.04Z",
            name: "Slovenia"
        },
        KP: {
            path: "M736.77,185.16l-0.92,-0.42l-0.88,0.62l-1.21,-0.88l0.96,-1.15l0.59,-2.59l-0.46,-0.74l-2.09,-0.77l1.64,-1.52l2.72,-1.58l1.58,-1.91l1.11,0.78l2.17,0.11l0.41,-0.5l-0.3,-1.22l3.52,-1.18l0.94,-1.4l0.98,1.08l-2.19,2.18l0.01,2.14l-1.06,0.54l-1.41,1.4l-1.7,0.52l-1.25,1.09l-0.14,1.98l0.94,0.45l1.15,1.04l-0.13,0.26l-2.6,0.29l-1.13,1.29l-1.22,0.08Z",
            name: "Dem. Rep. Korea"
        },
        SO: {
            path: "M525.13,288.48l-1.13,-1.57l-0.03,-8.86l2.66,-3.38l1.67,-0.13l2.13,-1.69l3.41,-0.23l7.08,-7.55l2.91,-3.69l0.08,-4.82l2.98,-0.67l1.24,-0.86l0.45,-0.0l-0.2,3.0l-1.21,3.62l-2.73,5.97l-2.13,3.65l-5.03,6.16l-8.56,6.4l-2.78,3.08l-0.8,1.56Z",
            name: "Somalia"
        },
        SN: {
            path: "M390.09,248.21l0.12,1.55l0.49,1.46l0.96,0.82l0.05,1.28l-1.26,-0.19l-0.75,0.33l-1.84,-0.61l-5.84,-0.13l-2.54,0.51l-0.22,-1.03l1.77,0.04l2.01,-0.91l1.03,0.48l1.09,0.04l1.29,-0.62l0.14,-0.58l-0.51,-0.74l-1.81,0.25l-1.13,-0.63l-0.79,0.04l-0.72,0.61l-2.31,0.06l-0.92,-1.77l-0.81,-0.64l0.64,-0.35l2.46,-3.74l1.04,0.19l1.38,-0.56l1.19,-0.02l2.72,1.37l3.03,3.48Z",
            name: "Senegal"
        },
        SL: {
            path: "M394.46,264.11l-1.73,1.98l-0.58,1.33l-2.07,-1.06l-1.22,-1.26l-0.65,-2.39l1.16,-0.96l0.67,-1.17l1.21,-0.52l1.66,0.0l1.03,1.64l0.52,2.41Z",
            name: "Sierra Leone"
        },
        SB: {
            path: "M826.69,311.6l-0.61,0.09l-0.2,-0.33l0.37,0.15l0.44,0.09ZM824.18,307.38l-0.26,-0.3l-0.31,-0.91l0.03,0.0l0.54,1.21ZM823.04,309.33l-1.66,-0.22l-0.2,-0.52l1.16,0.28l0.69,0.46ZM819.28,304.68l1.14,0.65l0.02,0.03l-0.81,-0.44l-0.35,-0.23Z",
            name: "Solomon Is."
        },
        SA: {
            path: "M537.53,210.34l2.0,0.24l0.9,1.32l1.49,-0.06l0.87,2.08l1.29,0.76l0.51,0.99l1.56,1.03l-0.1,1.9l0.32,0.9l1.58,2.47l0.76,0.53l0.7,-0.04l1.68,4.23l7.53,1.33l0.51,-0.29l0.77,1.25l-1.55,4.87l-7.29,2.52l-7.3,1.03l-2.34,1.17l-1.88,2.74l-0.76,0.28l-0.82,-0.78l-0.91,0.12l-2.88,-0.51l-3.51,0.25l-0.86,-0.56l-0.57,0.15l-0.66,1.27l0.16,1.11l-0.43,0.32l-0.93,-1.4l-0.33,-1.16l-1.23,-0.88l-1.27,-2.06l-0.78,-2.22l-1.73,-1.79l-1.14,-0.48l-1.54,-2.31l-0.21,-3.41l-1.44,-2.93l-1.27,-1.16l-1.33,-0.57l-1.31,-3.37l-0.77,-0.67l-0.97,-1.97l-2.8,-4.03l-1.06,-0.17l0.37,-1.96l0.2,-0.72l2.74,0.3l1.08,-0.84l0.6,-0.94l1.74,-0.35l0.65,-1.03l0.71,-0.4l0.1,-0.62l-2.06,-2.28l4.39,-1.22l0.48,-0.37l2.77,0.69l3.66,1.9l7.03,5.5l4.87,0.3Z",
            name: "Saudi Arabia"
        },
        SE: {
            path: "M480.22,89.3l-4.03,1.17l-2.43,2.86l0.26,2.57l-8.77,6.64l-1.78,5.79l1.78,2.68l2.22,1.96l-2.07,3.77l-2.72,1.13l-0.95,6.04l-1.29,3.01l-2.74,-0.31l-0.4,0.22l-1.31,2.59l-2.34,0.13l-0.75,-3.09l-2.08,-4.03l-1.83,-4.96l1.0,-1.93l2.14,-2.7l0.83,-4.45l-1.6,-2.17l-0.15,-4.94l1.48,-3.39l2.58,-0.15l0.87,-1.59l-0.78,-1.57l3.76,-5.59l4.04,-7.48l2.17,0.01l0.39,-0.29l0.57,-2.07l4.37,0.64l0.46,-0.34l0.33,-2.56l1.1,-0.13l6.94,4.87l0.06,6.32l0.66,1.36Z",
            name: "Sweden"
        },
        SD: {
            path: "M505.98,259.4l-0.34,-0.77l-1.17,-0.9l-0.26,-1.61l0.29,-1.81l-0.34,-0.46l-1.16,-0.17l-0.54,0.59l-1.23,0.11l-0.28,0.65l0.53,0.65l0.17,1.22l-2.44,3.0l-0.96,0.19l-2.39,-1.4l-0.95,0.52l-0.38,0.78l-1.11,0.41l-0.29,0.5l-1.94,0.0l-0.54,-0.52l-1.81,-0.09l-0.95,0.4l-2.45,-2.35l-2.07,0.54l-0.73,1.26l-0.6,2.1l-1.25,0.58l-0.75,-0.62l0.27,-2.65l-1.48,-1.78l-0.22,-1.48l-0.92,-0.96l-0.02,-1.29l-0.57,-1.16l-0.68,-0.16l0.69,-1.29l-0.18,-1.14l0.65,-0.62l0.03,-0.55l-0.36,-0.41l1.55,-2.97l1.91,0.16l0.43,-0.4l-0.1,-10.94l2.49,-0.01l0.4,-0.4l-0.0,-4.82l29.02,0.0l0.64,2.04l-0.49,0.66l0.36,2.69l0.93,3.16l2.12,1.55l-0.89,1.04l-1.72,0.39l-0.98,0.9l-1.43,5.65l0.24,1.15l-0.38,2.06l-0.96,2.38l-1.53,1.31l-1.32,2.91l-1.22,0.86l-0.37,1.34Z",
            name: "Sudan"
        },
        DO: {
            path: "M241.8,239.2l0.05,-0.65l-0.46,-0.73l0.42,-0.44l0.19,-1.0l-0.09,-1.53l1.66,0.01l1.99,0.63l0.33,0.67l1.28,0.19l0.33,0.76l1.0,0.08l0.8,0.62l-0.45,0.51l-1.13,-0.47l-1.88,-0.01l-1.27,0.59l-0.75,-0.55l-1.01,0.54l-0.79,1.4l-0.23,-0.61Z",
            name: "Dominican Rep."
        },
        DJ: {
            path: "M528.43,256.18l-0.45,0.66l-0.58,-0.25l-1.51,0.13l-0.18,-1.01l1.45,-1.95l0.83,0.17l0.77,-0.44l0.2,1.0l-1.2,0.51l-0.06,0.7l0.73,0.47Z",
            name: "Djibouti"
        },
        DK: {
            path: "M452.28,129.07l-1.19,2.24l-2.13,-1.6l-0.23,-0.95l2.98,-0.95l0.57,1.26ZM447.74,126.31l-0.26,0.57l-0.88,-0.07l-1.8,2.53l0.48,1.69l-1.09,0.36l-1.61,-0.39l-0.89,-1.69l-0.07,-3.43l0.96,-1.73l2.02,-0.2l1.09,-1.07l1.33,-0.67l-0.05,1.06l-0.73,1.41l0.3,1.0l1.2,0.64Z",
            name: "Denmark"
        },
        DE: {
            path: "M453.14,155.55l-0.55,-0.36l-1.2,-0.1l-1.87,0.57l-2.13,-0.13l-0.56,0.63l-0.86,-0.6l-0.96,0.09l-2.57,-0.93l-0.85,0.67l-1.47,-0.02l0.24,-1.75l1.23,-2.14l-0.28,-0.59l-3.52,-0.58l-0.92,-0.66l0.12,-1.2l-0.48,-0.88l0.27,-2.17l-0.37,-3.03l1.41,-0.22l0.63,-1.26l0.66,-3.19l-0.41,-1.18l0.26,-0.39l1.66,-0.15l0.33,0.54l0.62,0.07l1.7,-1.69l-0.54,-3.02l1.37,0.33l1.31,-0.37l0.31,1.18l2.25,0.71l-0.02,0.92l0.5,0.4l2.55,-0.65l1.34,-0.87l2.57,1.24l1.06,0.98l0.48,1.44l-0.57,0.74l-0.0,0.48l0.87,1.15l0.57,1.64l-0.14,1.29l0.82,1.7l-1.5,-0.07l-0.56,0.57l-4.47,2.15l-0.22,0.54l0.68,2.26l2.58,2.16l-0.66,1.11l-0.79,0.36l-0.23,0.43l0.32,1.87Z",
            name: "Germany"
        },
        YE: {
            path: "M528.27,246.72l0.26,-0.42l-0.22,-1.01l0.19,-1.5l0.92,-0.69l-0.07,-1.35l0.39,-0.75l1.01,0.47l3.34,-0.27l3.76,0.41l0.95,0.81l1.36,-0.58l1.74,-2.62l2.18,-1.09l6.86,-0.94l2.48,5.41l-1.64,0.76l-0.56,1.9l-6.23,2.16l-2.29,1.8l-1.93,0.05l-1.41,1.02l-4.24,0.74l-1.72,1.49l-3.28,0.19l-0.52,-1.18l0.02,-1.51l-1.34,-3.29Z",
            name: "Yemen"
        },
        AT: {
            path: "M462.89,152.8l0.04,2.25l-1.07,0.0l-0.33,0.63l0.36,0.51l-1.04,2.13l-2.02,0.07l-1.33,0.7l-5.29,-0.99l-0.47,-0.93l-0.44,-0.21l-2.47,0.55l-0.42,0.51l-3.18,-0.81l0.43,-0.91l1.12,0.78l0.6,-0.17l0.25,-0.58l1.93,0.12l1.86,-0.56l1.0,0.08l0.68,0.57l0.62,-0.15l0.26,-0.77l-0.3,-1.78l0.8,-0.44l0.68,-1.15l1.52,0.85l0.47,-0.06l1.34,-1.25l0.64,-0.17l1.81,0.92l1.28,-0.11l0.7,0.37Z",
            name: "Austria"
        },
        DZ: {
            path: "M441.46,188.44l-0.32,1.07l0.39,2.64l-0.54,2.16l-1.58,1.82l0.37,2.39l1.91,1.55l0.18,0.8l1.42,1.03l1.84,7.23l0.12,1.16l-0.57,5.0l0.2,1.51l-0.87,0.99l-0.02,0.51l1.41,1.86l0.14,1.2l0.89,1.48l0.5,0.16l0.98,-0.41l1.73,1.08l0.82,1.23l-8.22,4.81l-7.23,5.11l-3.43,1.13l-2.3,0.21l-0.28,-1.59l-2.56,-1.09l-0.67,-1.25l-26.12,-17.86l0.01,-3.47l3.77,-1.88l2.44,-0.41l2.12,-0.75l1.08,-1.42l2.81,-1.05l0.35,-2.08l1.33,-0.29l1.04,-0.94l3.47,-0.69l0.46,-1.08l-0.1,-0.45l-0.58,-0.52l-0.82,-2.81l-0.19,-1.83l-0.78,-1.49l2.03,-1.31l2.63,-0.48l1.7,-1.22l2.31,-0.84l8.24,-0.73l1.49,0.38l2.28,-1.1l2.46,-0.02l0.92,0.6l1.35,-0.05Z",
            name: "Algeria"
        },
        US: {
            path: "M892.72,99.2l1.31,0.53l1.41,-0.37l1.89,0.98l1.89,0.42l-1.32,0.58l-2.9,-1.53l-2.08,0.22l-0.26,-0.15l0.07,-0.67ZM183.22,150.47l0.37,1.47l1.12,0.85l4.23,0.7l2.39,0.98l2.17,-0.38l1.85,0.5l-1.55,0.65l-3.49,2.61l-0.16,0.77l0.5,0.39l2.33,-0.61l1.77,1.02l5.15,-2.4l-0.31,0.65l0.25,0.56l1.36,0.38l1.71,1.16l4.7,-0.88l0.67,0.85l1.31,0.21l0.58,0.58l-1.34,0.17l-2.18,-0.32l-3.6,0.89l-2.71,3.25l0.35,0.9l0.59,-0.0l0.55,-0.6l-1.36,4.65l0.29,3.09l0.67,1.58l0.61,0.45l1.77,-0.44l1.6,-1.96l0.14,-2.21l-0.82,-1.96l0.11,-1.13l1.19,-2.37l0.44,-0.33l0.48,0.75l0.4,-0.29l0.4,-1.37l0.6,-0.47l0.24,-0.8l1.69,0.49l1.65,1.08l-0.03,2.37l-1.27,1.13l-0.0,1.13l0.87,0.36l1.66,-1.29l0.5,0.17l0.5,2.6l-2.49,3.75l0.17,0.61l1.54,0.62l1.48,0.17l1.92,-0.44l4.72,-2.15l2.16,-1.8l-0.05,-1.24l0.75,-0.22l3.92,0.36l2.12,-1.05l0.21,-0.4l-0.28,-1.48l3.27,-2.4l8.32,-0.02l0.56,-0.82l1.9,-0.77l0.93,-1.51l0.74,-2.37l1.58,-1.98l0.92,0.62l1.47,-0.47l0.8,0.66l-0.0,4.09l1.96,2.6l-2.34,1.31l-5.37,2.09l-1.83,2.72l0.02,1.79l0.83,1.59l0.54,0.23l-6.19,0.94l-2.2,0.89l-0.23,0.48l0.45,0.29l2.99,-0.46l-2.19,0.56l-1.13,0.0l-0.15,-0.32l-0.48,0.08l-0.76,0.82l0.22,0.67l0.32,0.06l-0.41,1.62l-1.27,1.58l-1.48,-1.07l-0.49,-0.04l-0.16,0.46l0.52,1.58l0.61,0.59l0.03,0.79l-0.95,1.38l-1.21,-1.22l-0.27,-2.27l-0.35,-0.35l-0.42,0.25l-0.48,1.27l0.33,1.41l-0.97,-0.27l-0.48,0.24l0.18,0.5l1.52,0.83l0.1,2.52l0.79,0.51l0.52,3.42l-1.42,1.88l-2.47,0.8l-1.71,1.66l-1.31,0.25l-1.27,1.03l-0.43,0.99l-2.69,1.78l-2.64,3.03l-0.45,2.12l0.45,2.08l0.85,2.38l1.09,1.9l0.04,1.2l1.16,3.06l-0.18,2.69l-0.55,1.43l-0.47,0.21l-0.89,-0.23l-0.49,-1.18l-0.87,-0.56l-2.75,-5.16l0.48,-1.68l-0.72,-1.78l-2.01,-2.38l-1.12,-0.53l-2.72,1.18l-1.47,-1.35l-1.57,-0.68l-2.99,0.31l-2.17,-0.3l-2.0,0.19l-1.15,0.46l-0.19,0.58l0.39,0.63l0.14,1.34l-0.84,-0.2l-0.84,0.46l-1.58,-0.07l-2.08,-1.44l-2.09,0.33l-1.91,-0.62l-3.73,0.84l-2.39,2.07l-2.54,1.22l-1.45,1.41l-0.61,1.38l0.34,3.71l-0.29,0.02l-3.5,-1.33l-1.25,-3.11l-1.44,-1.5l-2.24,-3.56l-1.76,-1.09l-2.27,-0.01l-1.71,2.07l-1.76,-0.69l-1.16,-0.74l-1.52,-2.98l-3.93,-3.16l-4.34,-0.0l-0.4,0.4l-0.0,0.74l-6.5,0.02l-9.02,-3.14l-0.34,-0.71l-5.7,0.49l-0.43,-1.29l-1.62,-1.61l-1.14,-0.38l-0.55,-0.88l-1.28,-0.13l-1.01,-0.77l-2.22,-0.27l-0.43,-0.3l-0.36,-1.58l-2.4,-2.83l-2.01,-3.85l-0.06,-0.9l-2.92,-3.26l-0.33,-2.29l-1.3,-1.66l0.52,-2.37l-0.09,-2.57l-0.78,-2.3l0.95,-2.82l0.61,-5.68l-0.47,-4.27l-1.46,-4.08l3.19,0.79l1.26,2.83l0.69,0.08l0.69,-1.14l-1.1,-4.79l68.76,-0.0l0.4,-0.4l0.14,-0.86ZM32.44,67.52l1.73,1.97l0.55,0.05l0.99,-0.79l3.65,0.24l-0.09,0.62l0.32,0.45l3.83,0.77l2.61,-0.43l5.19,1.4l4.84,0.43l1.89,0.57l3.42,-0.7l6.14,1.87l-0.03,38.06l0.38,0.4l2.39,0.11l2.31,0.98l3.9,3.99l0.55,0.04l2.4,-2.03l2.16,-1.04l1.2,1.71l3.95,3.14l4.09,6.63l4.2,2.29l0.06,1.83l-1.02,1.23l-1.16,-1.08l-2.04,-1.03l-0.67,-2.89l-3.28,-3.03l-1.65,-3.57l-6.35,-0.32l-2.82,-1.01l-5.26,-3.85l-6.77,-2.04l-3.53,0.3l-4.81,-1.69l-3.25,-1.63l-2.78,0.8l-0.28,0.46l0.44,2.21l-3.91,0.96l-2.26,1.27l-2.3,0.65l-0.27,-1.65l1.05,-3.42l2.49,-1.09l0.16,-0.6l-0.69,-0.96l-0.55,-0.1l-3.19,2.12l-1.78,2.56l-3.55,2.61l-0.04,0.61l1.56,1.52l-2.07,2.29l-5.11,2.57l-0.77,1.66l-3.76,1.77l-0.92,1.73l-2.69,1.38l-1.81,-0.22l-6.95,3.32l-3.97,0.91l4.85,-2.5l2.59,-1.86l3.26,-0.52l1.19,-1.4l3.42,-2.1l2.59,-2.27l0.42,-2.68l1.23,-2.1l-0.04,-0.46l-0.45,-0.11l-2.68,1.03l-0.63,-0.49l-0.53,0.03l-1.05,1.04l-1.36,-1.54l-0.66,0.08l-0.32,0.62l-0.58,-1.14l-0.56,-0.16l-2.41,1.42l-1.07,-0.0l-0.17,-1.75l0.3,-1.71l-1.61,-1.33l-3.41,0.59l-1.96,-1.63l-1.57,-0.84l-0.15,-2.21l-1.7,-1.43l0.82,-1.88l1.99,-2.12l0.88,-1.92l1.71,-0.24l2.04,0.51l1.87,-1.77l1.91,0.25l1.91,-1.23l0.17,-0.43l-0.47,-1.82l-1.07,-0.7l1.39,-1.17l0.12,-0.45l-0.39,-0.26l-1.65,0.07l-2.66,0.88l-0.75,0.78l-1.92,-0.8l-3.46,0.44l-3.44,-0.91l-1.06,-1.61l-2.65,-1.99l2.91,-1.43l5.5,-2.0l1.52,0.0l-0.26,1.62l0.41,0.46l5.29,-0.16l0.3,-0.65l-2.03,-2.59l-3.14,-1.68l-1.79,-2.12l-2.4,-1.83l-3.09,-1.24l1.04,-1.69l4.23,-0.14l3.36,-2.07l0.73,-2.27l2.39,-1.99l2.42,-0.52l4.65,-1.97l2.46,0.23l3.71,-2.35l3.5,0.89ZM37.6,123.41l-2.25,1.23l-0.95,-0.69l-0.29,-1.24l3.21,-1.63l1.42,0.21l0.67,0.7l-1.8,1.42ZM31.06,234.03l0.98,0.47l0.74,0.87l-1.77,1.07l-0.44,-1.53l0.49,-0.89ZM29.34,232.07l0.18,0.05l0.08,0.05l-0.16,0.03l-0.11,-0.14ZM25.16,230.17l0.05,-0.03l0.18,0.22l-0.13,-0.01l-0.1,-0.18ZM5.89,113.26l-1.08,0.41l-2.21,-1.12l1.53,-0.4l1.62,0.28l0.14,0.83Z",
            name: "United States"
        },
        LV: {
            path: "M489.16,122.85l0.96,0.66l0.22,1.65l0.68,1.76l-3.65,1.7l-2.23,-1.58l-1.29,-0.26l-0.68,-0.77l-2.42,0.34l-4.16,-0.23l-2.47,0.9l0.06,-1.98l1.13,-2.06l1.95,-1.02l2.12,2.58l2.01,-0.07l0.38,-0.33l0.44,-2.52l1.76,-0.53l3.06,1.7l2.15,0.07Z",
            name: "Latvia"
        },
        UY: {
            path: "M286.85,372.74l-0.92,1.5l-2.59,1.44l-1.69,-0.52l-1.42,0.26l-2.39,-1.19l-1.52,0.08l-1.27,-1.3l0.16,-1.5l0.56,-0.79l-0.02,-2.73l1.21,-4.74l1.19,-0.21l2.37,2.0l1.08,0.03l4.36,3.17l1.22,1.6l-0.96,1.5l0.61,1.4Z",
            name: "Uruguay"
        },
        LB: {
            path: "M510.37,198.01l-0.88,0.51l1.82,-3.54l0.62,0.08l0.22,0.61l-1.13,0.88l-0.65,1.47Z",
            name: "Lebanon"
        },
        LA: {
            path: "M689.54,248.53l-1.76,-0.74l-0.49,0.15l-0.94,1.46l-1.32,-0.64l0.62,-0.98l0.11,-2.17l-2.04,-2.42l-0.25,-2.65l-1.9,-2.1l-2.15,-0.31l-0.78,0.91l-1.12,0.06l-1.05,-0.4l-2.06,1.2l-0.04,-1.59l0.61,-2.68l-0.36,-0.49l-1.35,-0.1l-0.11,-1.23l-0.96,-0.88l1.96,-1.89l0.39,0.36l1.33,0.07l0.42,-0.45l-0.34,-2.66l0.7,-0.21l1.28,1.81l1.11,2.35l0.36,0.23l2.82,0.02l0.71,1.67l-1.39,0.65l-0.72,0.93l0.13,0.6l2.91,1.51l3.6,5.25l1.88,1.78l0.56,1.62l-0.35,1.96Z",
            name: "Lao PDR"
        },
        TW: {
            path: "M724.01,226.68l-0.74,1.48l-0.9,-1.52l-0.25,-1.74l1.38,-2.44l1.73,-1.74l0.64,0.44l-1.85,5.52Z",
            name: "Taiwan"
        },
        TT: {
            path: "M266.64,259.32l0.28,-1.16l1.13,-0.22l-0.06,1.2l-1.35,0.18Z",
            name: "Trinidad and Tobago"
        },
        TR: {
            path: "M513.21,175.47l3.64,1.17l3.05,-0.44l2.1,0.26l3.11,-1.56l2.46,-0.13l2.19,1.33l0.33,0.82l-0.22,1.33l0.25,0.44l2.28,1.13l-1.17,0.57l-0.21,0.45l0.75,3.2l-0.41,1.16l1.13,1.92l-0.55,0.22l-0.9,-0.67l-2.91,-0.37l-1.24,0.46l-4.23,0.41l-2.81,1.05l-1.91,0.01l-1.52,-0.53l-2.58,0.75l-0.66,-0.45l-0.62,0.3l-0.12,1.45l-0.89,0.84l-0.47,-0.67l0.79,-1.3l-0.41,-0.2l-1.43,0.23l-2.0,-0.63l-2.02,1.65l-3.51,0.3l-2.13,-1.53l-2.7,-0.1l-0.86,1.24l-1.38,0.27l-2.29,-1.44l-2.71,-0.01l-1.37,-2.65l-1.68,-1.52l1.07,-1.99l-0.09,-0.49l-1.27,-1.12l2.37,-2.41l3.7,-0.11l1.28,-2.24l4.49,0.37l3.21,-1.97l2.81,-0.82l3.99,-0.06l4.29,2.07ZM488.79,176.72l-1.72,1.31l-0.5,-0.88l1.37,-2.57l-0.7,-0.85l1.7,-0.63l1.8,0.34l0.46,1.17l1.76,0.78l-2.87,0.32l-1.3,1.01Z",
            name: "Turkey"
        },
        LK: {
            path: "M624.16,268.99l-1.82,0.48l-0.99,-1.67l-0.42,-3.46l0.95,-3.43l1.21,0.98l2.26,4.19l-0.34,2.33l-0.85,0.58Z",
            name: "Sri Lanka"
        },
        TN: {
            path: "M448.1,188.24l-1.0,1.27l-0.02,1.32l0.84,0.88l-0.28,2.09l-1.53,1.32l-0.12,0.42l0.48,1.54l1.42,0.32l0.53,1.11l0.9,0.52l-0.11,1.67l-3.54,2.64l-0.1,2.38l-0.58,0.3l-0.96,-4.45l-1.54,-1.25l-0.16,-0.78l-1.92,-1.56l-0.18,-1.76l1.51,-1.62l0.59,-2.34l-0.38,-2.78l0.42,-1.21l2.45,-1.05l1.29,0.26l-0.06,1.11l0.58,0.38l1.47,-0.73Z",
            name: "Tunisia"
        },
        TL: {
            path: "M734.55,307.93l-0.1,-0.97l4.5,-0.86l-2.82,1.28l-1.59,0.55Z",
            name: "Timor-Leste"
        },
        TM: {
            path: "M553.03,173.76l-0.04,0.34l-0.09,-0.22l0.13,-0.12ZM555.87,172.66l0.45,-0.1l1.48,0.74l2.06,2.43l4.07,-0.18l0.38,-0.51l-0.32,-1.19l1.92,-0.94l1.91,-1.59l2.94,1.39l0.43,2.47l1.19,0.67l2.58,-0.13l0.62,0.4l1.32,3.12l4.54,3.44l2.67,1.45l3.06,1.14l-0.04,1.05l-1.33,-0.75l-0.59,0.19l-0.32,0.84l-2.2,0.81l-0.46,2.13l-1.21,0.74l-1.91,0.42l-0.73,1.33l-1.56,0.31l-2.22,-0.94l-0.2,-2.17l-0.38,-0.36l-1.73,-0.09l-2.76,-2.46l-2.14,-0.4l-2.84,-1.48l-1.78,-0.27l-1.24,0.53l-1.57,-0.08l-2.0,1.69l-1.7,0.43l-0.36,-1.58l0.36,-2.98l-0.22,-0.4l-1.65,-0.84l0.54,-1.69l-0.34,-0.52l-1.22,-0.13l0.36,-1.64l2.22,0.59l2.2,-0.95l0.12,-0.65l-1.77,-1.74l-0.66,-1.57Z",
            name: "Turkmenistan"
        },
        TJ: {
            path: "M597.75,178.82l-2.54,-0.44l-0.47,0.34l-0.24,1.7l0.43,0.45l2.64,-0.22l3.18,0.95l4.39,-0.41l0.56,2.37l0.52,0.29l0.67,-0.24l1.11,0.49l0.21,2.13l-3.76,-0.21l-1.8,1.32l-1.76,0.74l-0.61,-0.58l0.21,-2.23l-0.64,-0.49l-0.07,-0.93l-1.36,-0.66l-0.45,0.07l-1.08,1.01l-0.55,1.48l-1.31,-0.05l-0.95,1.16l-0.9,-0.35l-1.86,0.74l1.26,-2.83l-0.54,-2.17l-1.67,-0.82l0.33,-0.66l2.18,-0.04l1.19,-1.63l0.76,-1.79l2.43,-0.5l-0.26,1.0l0.73,1.05Z",
            name: "Tajikistan"
        },
        LS: {
            path: "M491.06,363.48l-0.49,0.15l-1.49,-1.67l1.1,-1.43l2.19,-1.44l1.51,1.27l-0.98,1.82l-1.23,0.38l-0.62,0.93Z",
            name: "Lesotho"
        },
        TH: {
            path: "M670.27,255.86l-1.41,3.87l0.15,2.0l0.38,0.36l1.38,0.07l0.9,2.04l0.55,2.34l1.4,1.44l1.61,0.38l0.96,0.97l-0.5,0.64l-1.1,0.2l-0.34,-1.18l-2.04,-1.1l-0.63,0.23l-0.63,-0.62l-0.48,-1.3l-2.56,-2.63l-0.73,0.41l0.95,-3.89l2.16,-4.22ZM670.67,254.77l-0.92,-2.18l-0.26,-2.61l-2.14,-3.06l0.71,-0.49l0.89,-2.59l-3.61,-5.45l0.87,-0.51l1.05,-2.58l1.74,-0.18l2.6,-1.59l0.76,0.56l0.13,1.39l0.37,0.36l1.23,0.09l-0.51,2.28l0.05,2.42l0.6,0.34l2.43,-1.42l0.77,0.39l1.47,-0.07l0.71,-0.88l1.48,0.14l1.71,1.88l0.25,2.65l1.92,2.11l-0.1,1.89l-0.61,0.86l-2.22,-0.33l-3.5,0.64l-1.6,2.12l0.36,2.58l-1.51,-0.79l-1.84,-0.01l0.28,-1.52l-0.4,-0.47l-2.21,0.01l-0.4,0.37l-0.19,2.74l-0.34,0.93Z",
            name: "Thailand"
        },
        TF: {
            path: "M596.68,420.38l-3.2,0.18l-0.05,-1.26l0.39,-1.41l1.3,0.78l2.08,0.35l-0.52,1.36Z",
            name: "Fr. S. Antarctic Lands"
        },
        TG: {
            path: "M422.7,257.63l-0.09,1.23l1.53,1.52l0.08,1.09l0.5,0.65l-0.11,5.62l0.49,1.47l-1.31,0.35l-1.02,-2.13l-0.18,-1.12l0.53,-2.19l-0.63,-1.16l-0.22,-3.68l-1.01,-1.4l0.07,-0.28l1.37,0.03Z",
            name: "Togo"
        },
        TD: {
            path: "M480.25,235.49l0.12,9.57l-2.1,0.05l-1.14,1.89l-0.69,1.63l0.34,0.73l-0.66,0.91l0.24,0.89l-0.86,1.95l0.45,0.5l0.6,-0.1l0.34,0.64l0.03,1.38l0.9,1.04l-1.45,0.43l-1.27,1.03l-1.83,2.76l-2.16,1.07l-2.31,-0.15l-0.86,0.25l-0.26,0.49l0.17,0.61l-2.11,1.68l-2.85,0.87l-1.09,-0.57l-0.73,0.66l-1.12,0.1l-1.1,-3.12l-1.25,-0.64l-1.22,-1.22l0.29,-0.64l3.01,0.04l0.35,-0.6l-1.3,-2.2l-0.08,-3.31l-0.97,-1.66l0.22,-1.04l-0.38,-0.48l-1.22,-0.04l0.0,-1.25l-0.98,-1.07l0.96,-3.01l3.25,-2.65l0.13,-3.33l0.95,-5.18l0.52,-1.07l-0.1,-0.48l-0.91,-0.78l-0.2,-0.96l-0.8,-0.58l-0.55,-3.65l2.1,-1.2l19.57,9.83Z",
            name: "Chad"
        },
        LY: {
            path: "M483.48,203.15l-0.75,1.1l0.29,1.39l-0.6,1.83l0.73,2.14l0.0,24.12l-2.48,0.01l-0.41,0.85l-19.41,-9.76l-4.41,2.28l-1.37,-1.33l-3.82,-1.1l-1.14,-1.65l-1.98,-1.23l-1.22,0.32l-0.66,-1.11l-0.17,-1.26l-1.28,-1.69l0.87,-1.19l-0.07,-4.34l0.43,-2.27l-0.86,-3.45l1.13,-0.76l0.22,-1.16l-0.2,-1.03l3.48,-2.61l0.29,-1.94l2.45,0.8l1.18,-0.21l1.98,0.44l3.15,1.18l1.37,2.54l5.72,1.67l2.64,1.35l1.61,-0.72l1.29,-1.34l-0.44,-2.34l0.66,-1.13l1.67,-1.21l1.57,-0.35l3.14,0.53l1.08,1.28l3.99,0.78l0.36,0.54Z",
            name: "Libya"
        },
        AE: {
            path: "M550.76,223.97l1.88,-0.4l3.84,0.02l4.78,-4.75l0.19,0.36l0.26,1.58l-0.81,0.01l-0.39,0.35l-0.08,2.04l-0.81,0.63l-0.01,0.96l-0.66,0.99l-0.39,1.41l-7.08,-1.25l-0.7,-1.96Z",
            name: "United Arab Emirates"
        },
        VE: {
            path: "M240.68,256.69l0.53,0.75l-0.02,1.06l-1.07,1.78l0.95,2.0l0.42,0.22l1.4,-0.44l0.56,-1.83l-0.77,-1.17l-0.1,-1.47l2.82,-0.93l0.26,-0.49l-0.28,-0.96l0.3,-0.28l0.66,1.31l1.96,0.26l1.4,1.22l0.08,0.68l0.39,0.35l4.81,-0.22l1.49,1.11l1.92,0.31l1.67,-0.84l0.22,-0.6l3.44,-0.14l-0.17,0.55l0.86,1.19l2.19,0.35l1.67,1.1l0.37,1.86l0.41,0.32l1.55,0.17l-1.66,1.35l-0.22,0.92l0.65,0.97l-1.67,0.54l-0.3,0.4l0.04,0.99l-0.56,0.57l-0.01,0.55l1.85,2.27l-0.66,0.69l-4.47,1.29l-0.72,0.54l-3.69,-0.9l-0.71,0.27l-0.02,0.7l0.91,0.53l-0.08,1.54l0.35,1.58l0.35,0.31l1.66,0.17l-1.3,0.52l-0.48,1.13l-2.68,0.91l-0.6,0.77l-1.57,0.13l-1.17,-1.13l-0.8,-2.52l-1.25,-1.26l1.02,-1.23l-1.29,-2.95l0.18,-1.62l1.0,-2.21l-0.2,-0.49l-1.14,-0.46l-4.02,0.36l-1.82,-2.1l-1.57,-0.33l-2.99,0.22l-1.06,-0.97l0.25,-1.23l-0.2,-1.01l-0.59,-0.69l-0.29,-1.06l-1.08,-0.39l0.78,-2.79l1.9,-2.11Z",
            name: "Venezuela"
        },
        AF: {
            path: "M600.7,188.88l-1.57,1.3l-0.1,0.48l0.8,2.31l-1.09,1.04l-0.03,1.27l-0.48,0.71l-2.16,-0.08l-0.37,0.59l0.78,1.48l-1.38,0.69l-1.06,1.69l0.06,1.7l-0.65,0.52l-0.91,-0.21l-1.91,0.36l-0.48,0.77l-1.88,0.13l-1.4,1.56l-0.18,2.32l-2.91,1.02l-1.65,-0.23l-0.71,0.55l-1.41,-0.3l-2.41,0.39l-3.52,-1.17l1.96,-2.35l-0.21,-1.78l-0.3,-0.34l-1.63,-0.4l-0.19,-1.58l-0.75,-2.03l0.95,-1.36l-0.19,-0.6l-0.73,-0.28l1.47,-4.8l2.14,0.9l2.12,-0.36l0.74,-1.34l1.77,-0.39l1.54,-0.92l0.63,-2.31l1.87,-0.5l0.49,-0.81l0.94,0.56l2.13,0.11l2.55,0.92l1.95,-0.83l0.65,0.43l0.56,-0.13l0.69,-1.12l1.57,-0.08l0.72,-1.66l0.79,-0.74l0.8,0.39l-0.17,0.56l0.71,0.58l-0.08,2.39l1.11,0.95ZM601.37,188.71l1.73,-0.71l1.43,-1.18l4.03,0.35l-2.23,0.74l-4.95,0.8Z",
            name: "Afghanistan"
        },
        IQ: {
            path: "M530.82,187.47l0.79,0.66l1.26,-0.28l1.46,3.08l1.63,0.94l0.14,1.23l-1.22,1.05l-0.53,2.52l1.73,2.67l3.12,1.62l1.15,1.88l-0.38,1.85l0.39,0.48l0.41,-0.0l0.02,1.07l0.76,0.94l-2.47,-0.1l-1.71,2.44l-4.31,-0.2l-7.02,-5.48l-3.73,-1.94l-2.88,-0.73l-0.85,-2.87l5.45,-3.02l0.95,-3.43l-0.19,-1.96l1.27,-0.7l1.22,-1.7l0.87,-0.36l2.69,0.34Z",
            name: "Iraq"
        },
        IS: {
            path: "M384.14,88.06l-0.37,2.61l2.54,2.51l-2.9,2.75l-9.19,3.4l-9.25,-1.66l1.7,-1.22l-0.1,-0.7l-4.05,-1.47l2.96,-0.53l0.33,-0.43l-0.11,-1.2l-0.33,-0.36l-4.67,-0.85l1.28,-2.04l3.45,-0.56l3.77,2.72l0.44,0.02l3.64,-2.16l3.3,1.08l3.98,-2.16l3.58,0.26Z",
            name: "Iceland"
        },
        IR: {
            path: "M533.43,187.16l-1.27,-2.15l0.42,-0.98l-0.71,-3.04l1.03,-0.5l0.33,0.83l1.26,1.35l2.05,0.51l1.11,-0.16l2.89,-2.11l0.62,-0.14l0.39,0.46l-0.72,1.2l0.06,0.49l1.56,1.53l0.65,0.04l0.67,1.81l2.56,0.83l1.87,1.48l3.69,0.49l3.91,-0.76l0.47,-0.73l2.17,-0.6l1.66,-1.54l1.51,0.08l1.18,-0.53l1.59,0.24l2.83,1.48l1.88,0.3l2.77,2.47l1.77,0.18l0.18,1.99l-1.68,5.49l0.24,0.5l0.61,0.23l-0.82,1.48l0.8,2.18l0.19,1.71l0.3,0.34l1.63,0.4l0.15,1.32l-2.15,2.35l-0.01,0.53l2.21,3.03l2.34,1.24l0.06,2.14l1.24,0.72l0.11,0.69l-3.31,1.27l-1.08,3.03l-9.68,-1.68l-0.99,-3.05l-1.43,-0.73l-2.17,0.46l-2.47,1.26l-2.83,-0.82l-2.46,-2.02l-2.41,-0.8l-3.42,-6.06l-0.48,-0.2l-1.18,0.39l-1.44,-0.82l-0.5,0.08l-0.65,0.74l-0.97,-1.01l-0.02,-1.31l-0.71,-0.39l0.26,-1.81l-1.29,-2.11l-3.13,-1.63l-1.58,-2.43l0.5,-1.9l1.31,-1.26l-0.19,-1.66l-1.74,-1.1l-1.57,-3.3Z",
            name: "Iran"
        },
        AM: {
            path: "M536.99,182.33l-0.28,0.03l-1.23,-2.13l-0.93,0.01l-0.62,-0.66l-0.69,-0.07l-0.96,-0.81l-1.56,-0.62l0.19,-1.12l-0.26,-0.79l2.72,-0.36l1.09,1.01l-0.17,0.92l1.02,0.78l-0.47,0.62l0.08,0.56l2.04,1.23l0.04,1.4Z",
            name: "Armenia"
        },
        IT: {
            path: "M451.59,158.63l3.48,0.94l-0.21,1.17l0.3,0.83l-1.49,-0.24l-2.04,1.1l-0.21,0.39l0.13,1.45l-0.25,1.12l0.82,1.57l2.39,1.63l1.31,2.54l2.79,2.43l2.05,0.08l0.21,0.23l-0.39,0.33l0.09,0.67l4.05,1.97l2.17,1.76l-0.16,0.36l-1.17,-1.08l-2.18,-0.49l-0.44,0.2l-1.05,1.91l0.14,0.54l1.57,0.95l-0.19,0.98l-1.06,0.33l-1.25,2.34l-0.37,0.08l0.0,-0.33l1.0,-2.45l-1.73,-3.17l-1.12,-0.51l-0.88,-1.33l-1.51,-0.51l-1.27,-1.25l-1.75,-0.18l-4.12,-3.21l-1.62,-1.65l-1.03,-3.19l-3.53,-1.36l-1.3,0.51l-1.69,1.41l0.16,-0.72l-0.28,-0.47l-1.14,-0.33l-0.53,-1.96l0.72,-0.78l0.04,-0.48l-0.65,-1.17l0.8,0.39l1.4,-0.23l1.11,-0.84l0.52,0.35l1.19,-0.1l0.75,-1.2l1.53,0.33l1.36,-0.56l0.35,-1.14l1.08,0.32l0.68,-0.64l1.98,-0.44l0.42,0.82ZM459.19,184.75l-0.65,1.65l0.32,1.05l-0.31,0.89l-1.5,-0.85l-4.5,-1.67l0.19,-0.82l2.67,0.23l3.78,-0.48ZM443.93,176.05l1.18,1.66l-0.3,3.32l-1.06,-0.01l-0.77,0.73l-0.53,-0.44l-0.1,-3.37l-0.39,-1.22l1.04,0.01l0.92,-0.68Z",
            name: "Italy"
        },
        VN: {
            path: "M690.56,230.25l-2.7,1.82l-2.09,2.46l-0.63,1.95l4.31,6.45l2.32,1.65l1.43,1.94l1.11,4.59l-0.32,4.24l-1.93,1.54l-2.84,1.61l-2.11,2.15l-2.73,2.06l-0.59,-1.05l0.63,-1.53l-0.13,-0.47l-1.34,-1.04l1.51,-0.71l2.55,-0.18l0.3,-0.63l-0.82,-1.14l4.0,-2.07l0.31,-3.05l-0.57,-1.77l0.42,-2.66l-0.73,-1.97l-1.86,-1.76l-3.63,-5.29l-2.72,-1.46l0.36,-0.47l1.5,-0.64l0.21,-0.52l-0.97,-2.27l-0.37,-0.24l-2.83,-0.02l-2.24,-3.9l0.83,-0.4l4.39,-0.29l2.06,-1.31l1.15,0.89l1.88,0.4l-0.17,1.51l1.35,1.16l1.67,0.45Z",
            name: "Vietnam"
        },
        AR: {
            path: "M249.29,428.93l-2.33,-0.52l-5.83,-0.43l-0.89,-1.66l0.05,-2.37l-0.45,-0.4l-1.43,0.18l-0.67,-0.91l-0.2,-3.13l1.88,-1.47l0.79,-2.04l-0.25,-1.7l1.3,-2.68l0.91,-4.15l-0.22,-1.69l0.85,-0.45l0.2,-0.44l-0.27,-1.16l-0.98,-0.68l0.59,-0.92l-0.05,-0.5l-1.04,-1.07l-0.52,-3.1l0.97,-0.86l-0.42,-3.58l1.2,-5.43l1.38,-0.98l0.16,-0.43l-0.75,-2.79l-0.01,-2.43l1.78,-1.75l0.06,-2.57l1.43,-2.85l0.01,-2.58l-0.69,-0.74l-1.09,-4.52l1.47,-2.7l-0.18,-2.79l0.85,-2.35l1.59,-2.46l1.73,-1.64l0.05,-0.52l-0.6,-0.84l0.44,-0.85l-0.07,-4.19l2.7,-1.44l0.86,-2.75l-0.21,-0.71l1.76,-2.01l2.9,0.57l1.38,1.78l0.68,-0.08l0.87,-1.87l2.39,0.09l4.95,4.77l2.17,0.49l3.0,1.92l2.47,1.0l0.25,0.82l-2.37,3.93l0.23,0.59l5.39,1.16l2.12,-0.44l2.45,-2.16l0.5,-2.38l0.76,-0.31l0.98,1.2l-0.04,1.8l-3.67,2.51l-2.85,2.66l-3.43,3.88l-1.3,5.07l0.01,2.72l-0.54,0.73l-0.36,3.28l3.14,2.64l-0.16,2.11l1.4,1.11l-0.1,1.09l-2.29,3.52l-3.55,1.49l-4.92,0.6l-2.71,-0.29l-0.43,0.51l0.5,1.65l-0.49,2.1l0.38,1.42l-1.19,0.83l-2.36,0.38l-2.3,-1.04l-1.38,0.83l0.41,3.64l1.69,0.91l1.4,-0.71l0.36,0.76l-2.04,0.86l-2.01,1.89l-0.97,4.63l-2.34,0.1l-2.09,1.78l-0.61,2.75l2.46,2.31l2.17,0.63l-0.7,2.32l-2.83,1.73l-1.73,3.86l-2.17,1.22l-1.16,1.67l0.75,3.76l1.04,1.28ZM256.71,438.88l-2.0,0.15l-1.4,-1.22l-3.82,-0.1l-0.0,-5.83l1.6,3.05l3.26,2.07l3.08,0.78l-0.71,1.1Z",
            name: "Argentina"
        },
        AU: {
            path: "M705.8,353.26l0.26,0.04l0.17,-0.47l-0.48,-1.42l0.92,1.11l0.45,0.15l0.27,-0.39l-0.1,-1.56l-1.98,-3.63l1.09,-3.31l-0.24,-1.57l0.34,-0.62l0.38,1.06l0.43,-0.19l0.99,-1.7l1.91,-0.83l1.29,-1.15l1.81,-0.91l0.96,-0.17l0.92,0.26l1.92,-0.95l1.47,-0.28l1.03,-0.8l1.43,0.04l2.78,-0.84l1.36,-1.15l0.71,-1.45l1.41,-1.26l0.3,-2.58l1.27,-1.59l0.78,1.65l0.54,0.19l1.07,-0.51l0.15,-0.6l-0.73,-1.0l0.45,-0.71l0.78,0.39l0.58,-0.3l0.28,-1.82l1.87,-2.14l1.12,-0.39l0.28,-0.58l0.62,0.17l0.53,-0.73l1.87,-0.57l1.65,1.05l1.35,1.48l3.39,0.38l0.43,-0.54l-0.46,-1.23l1.05,-1.79l1.04,-0.61l0.14,-0.55l-0.25,-0.41l0.88,-1.17l1.31,-0.77l1.3,0.27l2.1,-0.48l0.31,-0.4l-0.05,-1.3l-0.92,-0.77l1.48,0.56l1.41,1.07l2.11,0.65l0.81,-0.2l1.4,0.7l1.69,-0.66l0.8,0.19l0.64,-0.33l0.71,0.77l-1.33,1.94l-0.71,0.07l-0.35,0.51l0.24,0.86l-1.52,2.35l0.12,1.05l2.15,1.65l1.97,0.85l3.04,2.36l1.97,0.65l0.55,0.88l2.72,0.85l1.84,-1.1l2.07,-5.97l-0.42,-3.59l0.3,-1.73l0.47,-0.87l-0.31,-0.68l1.09,-3.28l0.46,-0.47l0.4,0.71l0.16,1.51l0.65,0.52l0.16,1.04l0.85,1.21l0.12,2.38l0.9,2.0l0.57,0.18l1.3,-0.78l1.69,1.7l-0.2,1.08l0.53,2.2l0.39,1.3l0.68,0.48l0.6,1.95l-0.19,1.48l0.81,1.76l6.01,3.69l-0.11,0.76l1.38,1.58l0.95,2.77l0.58,0.22l0.72,-0.41l0.8,0.9l0.61,0.01l0.46,2.41l4.81,4.71l0.66,2.02l-0.07,3.31l1.14,2.2l-0.13,2.24l-1.1,3.68l0.03,1.64l-0.47,1.89l-1.05,2.4l-1.9,1.47l-1.72,3.51l-2.38,6.09l-0.24,2.82l-1.14,0.8l-2.85,0.15l-2.31,1.19l-2.51,2.25l-3.09,-1.57l0.3,-1.15l-0.54,-0.47l-1.5,0.63l-2.01,1.94l-7.12,-2.18l-1.48,-1.63l-1.14,-3.74l-1.45,-1.26l-1.81,-0.26l0.56,-1.18l-0.61,-2.1l-0.72,-0.1l-1.14,1.82l-0.9,0.21l0.63,-0.82l0.36,-1.55l0.92,-1.31l-0.13,-2.34l-0.7,-0.22l-2.0,2.34l-1.51,0.93l-0.94,2.01l-1.35,-0.81l-0.02,-1.52l-1.57,-2.04l-1.09,-0.88l0.24,-0.33l-0.14,-0.59l-3.21,-1.69l-1.83,-0.12l-2.54,-1.35l-4.58,0.28l-6.02,1.9l-2.53,-0.13l-2.62,1.41l-2.13,0.63l-1.49,2.6l-3.49,0.31l-2.29,-0.5l-3.48,0.43l-1.6,1.47l-0.81,-0.04l-2.37,1.63l-3.26,-0.1l-3.72,-2.21l0.04,-1.05l1.19,-0.46l0.49,-0.89l0.21,-2.97l-0.28,-1.64l-1.34,-2.86l-0.38,-1.47l0.05,-1.72l-0.95,-1.7l-0.18,-0.97l-1.01,-0.99l-0.29,-1.98l-1.13,-1.75ZM784.92,393.44l2.65,1.02l3.23,-0.96l1.09,0.14l0.15,3.06l-0.85,1.13l-0.17,1.63l-0.87,-0.24l-1.57,1.91l-1.68,-0.18l-1.4,-2.36l-0.37,-2.04l-1.39,-2.51l0.04,-0.8l1.15,0.18Z",
            name: "Australia"
        },
        IL: {
            path: "M507.76,203.05l0.4,-0.78l0.18,0.4l-0.33,1.03l0.52,0.44l0.68,-0.22l-0.86,3.6l-1.16,-3.32l0.59,-0.74l-0.03,-0.41ZM508.73,200.34l0.37,-1.02l0.64,0.0l0.52,-0.51l-0.49,1.53l-0.56,-0.24l-0.48,0.23Z",
            name: "Israel"
        },
        IN: {
            path: "M623.34,207.03l-1.24,1.04l-0.97,2.55l0.22,0.51l8.04,3.87l3.42,0.37l1.57,1.38l4.92,0.88l2.18,-0.04l0.38,-0.3l0.29,-1.24l-0.32,-1.64l0.14,-0.87l0.82,-0.31l0.45,2.48l2.28,1.02l1.77,-0.38l4.14,0.1l0.38,-0.36l0.18,-1.66l-0.5,-0.65l1.37,-0.29l2.25,-1.99l2.7,-1.62l1.93,0.62l1.8,-0.98l0.79,1.14l-0.68,0.91l0.26,0.63l2.42,0.36l0.09,0.47l-0.83,0.75l0.13,1.07l-1.52,-0.29l-3.24,1.86l-0.13,1.78l-1.32,2.14l-0.18,1.39l-0.93,1.82l-1.64,-0.5l-0.52,0.37l-0.09,2.63l-0.56,1.11l0.19,0.81l-0.53,0.27l-1.18,-3.73l-1.08,-0.27l-0.38,0.31l-0.24,1.0l-0.66,-0.66l0.54,-1.06l1.22,-0.34l1.15,-2.25l-0.24,-0.56l-1.57,-0.47l-4.34,-0.28l-0.18,-1.56l-0.35,-0.35l-1.11,-0.12l-1.91,-1.12l-0.56,0.17l-0.88,1.82l0.11,0.49l1.36,1.07l-1.09,0.69l-0.69,1.11l0.18,0.56l1.24,0.57l-0.32,1.54l0.85,1.94l0.36,2.01l-0.22,0.59l-4.58,0.52l-0.33,0.42l0.13,1.8l-1.17,1.36l-3.65,1.81l-2.79,3.03l-4.32,3.28l-0.18,1.27l-4.65,1.79l-0.77,2.16l0.64,5.3l-1.06,2.49l-0.01,3.94l-1.24,0.28l-1.14,1.93l0.39,0.84l-1.68,0.53l-1.04,1.83l-0.65,0.47l-2.06,-2.05l-2.1,-6.02l-2.2,-3.64l-1.05,-4.75l-2.29,-3.57l-1.76,-8.2l0.01,-3.11l-0.49,-2.53l-0.55,-0.29l-3.53,1.52l-1.53,-0.27l-2.86,-2.77l0.85,-0.67l0.08,-0.55l-0.74,-1.03l-2.67,-2.06l1.24,-1.32l5.34,0.01l0.39,-0.49l-0.5,-2.29l-1.42,-1.46l-0.27,-1.93l-1.43,-1.2l2.31,-2.37l3.05,0.06l2.62,-2.85l1.6,-2.81l2.4,-2.73l0.07,-2.04l1.97,-1.48l-0.02,-0.65l-1.93,-1.31l-0.82,-1.78l-0.8,-2.21l0.9,-0.89l3.59,0.65l2.92,-0.42l2.33,-2.19l2.31,2.85l-0.24,2.13l0.99,1.59l-0.05,0.82l-1.34,-0.28l-0.47,0.48l0.7,3.06l2.62,1.99l2.99,1.65Z",
            name: "India"
        },
        TZ: {
            path: "M495.56,296.42l2.8,-3.12l-0.02,-0.81l-0.64,-1.3l0.68,-0.52l0.14,-1.47l-0.76,-1.25l0.31,-0.11l2.26,0.03l-0.51,2.76l0.76,1.3l0.5,0.12l1.05,-0.53l1.19,-0.12l0.61,0.24l1.43,-0.62l0.1,-0.67l-0.71,-0.62l1.57,-1.7l8.65,4.86l0.32,1.53l3.34,2.33l-1.05,2.8l0.13,1.61l1.63,1.12l-0.6,1.76l-0.01,2.33l1.89,4.03l0.57,0.43l-1.46,1.08l-2.61,0.94l-1.43,-0.04l-1.06,0.77l-2.29,0.36l-2.87,-0.68l-0.83,0.07l-0.63,-0.75l-0.31,-2.78l-1.32,-1.35l-3.25,-0.77l-3.96,-1.58l-1.18,-2.41l-0.32,-1.75l-1.76,-1.49l0.42,-1.05l-0.44,-0.89l0.08,-0.96l-0.46,-0.58l0.06,-0.56Z",
            name: "Tanzania"
        },
        AZ: {
            path: "M539.29,175.73l1.33,0.32l1.94,-1.8l2.3,3.34l1.43,0.43l-1.26,0.15l-0.35,0.32l-0.8,3.14l-0.99,0.96l0.05,1.11l-1.26,-1.13l0.7,-1.18l-0.04,-0.47l-0.74,-0.86l-1.48,0.15l-2.34,1.71l-0.03,-1.27l-2.03,-1.35l0.47,-0.62l-0.08,-0.56l-1.03,-0.79l0.29,-0.43l-0.14,-0.58l-1.13,-0.86l1.89,0.68l1.69,0.06l0.37,-0.87l-0.81,-1.37l0.42,0.06l1.63,1.72ZM533.78,180.57l0.61,0.46l0.69,-0.0l0.59,1.15l-0.68,-0.15l-1.21,-1.45Z",
            name: "Azerbaijan"
        },
        IE: {
            path: "M405.08,135.42l0.35,2.06l-1.75,2.78l-4.22,1.88l-2.84,-0.4l1.73,-3.0l-1.18,-3.53l4.6,-3.74l0.32,1.15l-0.49,1.74l0.4,0.51l1.47,-0.04l1.6,0.6Z",
            name: "Ireland"
        },
        ID: {
            path: "M756.47,287.89l0.69,4.01l2.79,1.78l0.51,-0.1l2.04,-2.59l2.71,-1.43l2.05,-0.0l3.9,1.73l2.46,0.45l0.08,15.12l-1.75,-1.54l-2.54,-0.51l-0.88,0.71l-2.32,0.06l0.69,-1.33l1.45,-0.64l0.23,-0.46l-0.65,-2.74l-1.24,-2.21l-5.04,-2.29l-2.09,-0.23l-3.68,-2.27l-0.55,0.13l-0.65,1.07l-0.52,0.12l-0.55,-1.89l-1.21,-0.78l1.84,-0.62l1.72,0.05l0.39,-0.52l-0.21,-0.66l-0.38,-0.28l-3.45,-0.0l-1.13,-1.48l-2.1,-0.43l-0.52,-0.6l2.69,-0.48l1.28,-0.78l3.66,0.94l0.3,0.71ZM757.91,300.34l-0.62,0.82l-0.1,-0.8l0.59,-1.12l0.13,1.1ZM747.38,292.98l0.34,0.72l-1.22,-0.57l-4.68,-0.1l0.27,-0.62l2.78,-0.09l2.52,0.67ZM741.05,285.25l-0.67,-2.88l0.64,-2.01l0.41,0.86l1.21,0.18l0.16,0.7l-0.1,1.68l-0.84,-0.16l-0.46,0.3l-0.34,1.34ZM739.05,293.5l-0.5,0.44l-1.34,-0.36l-0.17,-0.37l1.73,-0.08l0.27,0.36ZM721.45,284.51l-0.19,1.97l2.24,2.23l0.54,0.02l1.27,-1.07l2.75,-0.5l-0.9,1.21l-2.11,0.93l-0.16,0.6l2.22,3.01l-0.3,1.07l1.36,1.74l-2.26,0.85l-0.28,-0.31l0.12,-1.19l-1.64,-1.34l0.17,-2.23l-0.56,-0.39l-1.67,0.76l-0.23,0.39l0.3,6.17l-1.1,0.25l-0.69,-0.47l0.64,-2.21l-0.39,-2.42l-0.39,-0.34l-0.8,-0.01l-0.58,-1.29l0.98,-1.6l0.35,-1.96l1.32,-3.87ZM728.59,296.27l0.38,0.49l-0.02,1.28l-0.88,0.49l-0.53,-0.47l1.04,-1.79ZM729.04,286.98l0.27,-0.05l-0.02,0.13l-0.24,-0.08ZM721.68,284.05l0.16,-0.32l1.89,-1.65l1.83,0.68l3.16,0.35l2.94,-0.1l2.39,-1.66l-1.73,2.13l-1.66,0.43l-2.41,-0.48l-4.17,0.13l-2.39,0.51ZM730.55,310.47l1.11,-1.93l2.03,-0.82l0.08,0.62l-1.45,1.67l-1.77,0.46ZM728.12,305.88l-0.1,0.38l-3.46,0.66l-2.91,-0.27l-0.0,-0.25l1.54,-0.41l1.66,0.73l1.67,-0.19l1.61,-0.65ZM722.9,310.24l-0.64,0.03l-2.26,-1.2l1.11,-0.24l1.78,1.41ZM716.26,305.77l0.88,0.51l1.28,-0.17l0.2,0.35l-4.65,0.73l0.39,-0.67l1.15,-0.02l0.75,-0.73ZM711.66,293.84l-0.38,-0.16l-2.54,1.01l-1.12,-1.44l-1.69,-0.13l-1.16,-0.75l-3.04,0.77l-1.1,-1.15l-3.31,-0.11l-0.35,-3.05l-1.35,-0.95l-1.11,-1.98l-0.33,-2.06l0.27,-2.14l0.9,-1.01l0.37,1.15l2.09,1.49l1.53,-0.48l1.82,0.08l1.38,-1.19l1.0,-0.18l2.28,0.67l2.26,-0.53l1.52,-3.64l1.01,-0.99l0.78,-2.57l4.1,0.3l-1.11,1.77l0.02,0.46l1.7,2.2l-0.23,1.39l2.07,1.71l-2.33,0.42l-0.88,1.9l0.1,2.05l-2.4,1.9l-0.06,2.45l-0.7,2.79ZM692.58,302.03l0.35,0.26l4.8,0.25l0.78,-0.97l4.17,1.09l1.13,1.68l3.69,0.45l2.13,1.04l-1.8,0.6l-2.77,-0.99l-4.8,-0.12l-5.24,-1.41l-1.84,-0.25l-1.11,0.3l-4.26,-0.97l-0.7,-1.14l-1.59,-0.13l1.18,-1.65l2.74,0.13l2.87,1.13l0.26,0.68ZM685.53,299.17l-2.22,0.04l-2.06,-2.03l-3.15,-2.01l-2.93,-3.51l-3.11,-5.33l-2.2,-2.12l-1.64,-4.06l-2.32,-1.69l-1.27,-2.07l-1.96,-1.5l-2.51,-2.65l-0.11,-0.66l4.81,0.53l2.15,2.38l3.31,2.74l2.35,2.66l2.7,0.17l1.95,1.59l1.54,2.17l1.59,0.95l-0.84,1.71l0.15,0.52l1.44,0.87l0.79,0.1l0.4,1.58l0.87,1.4l1.96,0.39l1.0,1.31l-0.6,3.01l-0.09,3.5Z",
            name: "Indonesia"
        },
        UA: {
            path: "M492.5,162.44l1.28,-2.49l1.82,0.19l0.66,-0.23l0.09,-0.71l-0.25,-0.75l-0.79,-0.72l-0.33,-1.21l-0.86,-0.62l-0.02,-1.19l-1.13,-0.86l-1.15,-0.19l-2.04,-1.0l-1.66,0.32l-0.66,0.47l-0.92,-0.0l-0.84,0.78l-2.48,0.7l-1.18,-0.71l-3.07,-0.36l-0.89,0.43l-0.24,-0.55l-1.11,-0.7l0.35,-0.93l1.26,-1.02l-0.54,-1.23l2.04,-2.43l1.4,-0.62l0.25,-1.19l-1.04,-2.39l0.83,-0.13l1.28,-0.84l1.8,-0.07l2.47,0.26l2.86,0.81l1.88,0.06l0.86,0.44l1.04,-0.41l0.77,0.66l2.18,-0.15l0.92,0.3l0.52,-0.34l0.15,-1.53l0.56,-0.54l2.85,-0.05l0.84,-0.72l3.04,-0.18l1.23,1.46l-0.48,0.77l0.21,1.03l0.36,0.32l1.8,0.14l0.93,2.08l3.18,1.15l1.94,-0.45l1.67,1.49l1.4,-0.03l3.35,0.96l0.02,0.54l-0.96,1.59l0.47,1.97l-0.26,0.7l-2.36,0.28l-1.29,0.89l-0.23,1.38l-1.83,0.27l-1.58,0.97l-2.41,0.21l-2.16,1.17l-0.21,0.38l0.34,2.26l1.23,0.75l2.13,-0.08l-0.14,0.31l-2.65,0.53l-3.23,1.69l-0.87,-0.39l0.42,-1.1l-0.25,-0.52l-2.21,-0.73l2.35,-1.06l0.12,-0.65l-0.93,-0.82l-3.62,-0.74l-0.13,-0.89l-0.46,-0.34l-2.61,0.59l-0.91,1.69l-1.71,2.04l-0.86,-0.4l-1.62,0.27Z",
            name: "Ukraine"
        },
        QA: {
            path: "M549.33,221.64l-0.76,-0.23l-0.14,-1.64l0.84,-1.29l0.47,0.52l0.04,1.34l-0.45,1.3Z",
            name: "Qatar"
        },
        MZ: {
            path: "M508.58,318.75l-0.34,-2.57l0.51,-2.05l3.55,0.63l2.5,-0.38l1.02,-0.76l1.49,0.01l2.74,-0.98l1.66,-1.2l0.5,9.24l0.41,1.23l-0.68,1.67l-0.93,1.71l-1.5,1.5l-5.16,2.28l-2.78,2.73l-1.02,0.53l-1.71,1.8l-0.98,0.57l-0.35,2.41l1.16,1.94l0.49,2.17l0.43,0.31l-0.06,2.06l-0.39,1.17l0.5,0.72l-0.25,0.73l-0.92,0.83l-5.12,2.39l-1.22,1.36l0.21,1.13l0.58,0.39l-0.11,0.72l-1.22,-0.01l-0.73,-2.97l0.42,-3.09l-1.78,-5.37l2.49,-2.81l0.69,-1.89l0.44,-0.43l0.28,-1.53l-0.39,-0.93l0.59,-3.65l-0.01,-3.26l-1.49,-1.16l-1.2,-0.22l-1.74,-1.17l-1.92,0.01l-0.29,-2.08l7.06,-1.96l1.28,1.09l0.89,-0.1l0.67,0.44l0.1,0.73l-0.51,1.29l0.19,1.81l1.75,1.83l0.65,-0.13l0.71,-1.65l1.17,-0.86l-0.26,-3.47l-1.05,-1.85l-1.04,-0.94Z",
            name: "Mozambique"
        }
    },
    height: 440.70631074413296,
    projection: {
        type: "mill",
        centralMeridian: 11.5
    },
    width: 900
});